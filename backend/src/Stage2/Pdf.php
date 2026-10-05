<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class Pdf
{
    public static function textPages(string $bytes):array
    {
        if(!str_starts_with($bytes,'%PDF-')||!str_contains($bytes,'/BaseFont /DejaVuSans'))return[];
        preg_match_all('~BT /F1 10 Tf 14 TL 48 790 Td\n(.*?)ET\nBT /F1 8 Tf 48 35 Td~s',$bytes,$streams);$pages=[];
        foreach($streams[1]as$stream){preg_match_all('~<([0-9a-f]*)> Tj T\*~',$stream,$lines);$page=[];foreach($lines[1]as$hex){if(strlen($hex)%4)return[];$page[]=mb_convert_encoding(hex2bin($hex),'UTF-8','UTF-16BE');}$pages[]=$page;}
        return$pages;
    }

    public static function render(array $paragraphs):string
    {
        static $compact,$complete;$needsFull=(bool)preg_match('/[^\x{0000}-\x{00FF}\x{0400}-\x{052F}\x{2000}-\x{203F}\x{20AC}\x{20BD}\x{2116}\x{2190}\x{2192}\x{25CF}]/u',implode(' ',array_map('strval',$paragraphs)));$font=$needsFull?($complete??=file_get_contents(dirname(__DIR__,2).'/assets/fonts/DejaVuSans.ttf')):($compact??=base64_decode(file_get_contents(dirname(__DIR__,2).'/assets/fonts/DejaVuSansWorkspace.base64'),true));$u16=fn($p)=>unpack('n',substr($font,$p,2))[1];$u32=fn($p)=>unpack('N',substr($font,$p,4))[1];$tables=[];for($i=0;$i<$u16(4);$i++){$p=12+$i*16;$tables[substr($font,$p,4)]=$u32($p+8);}
        $cmap=$tables['cmap'];$format=null;for($i=0;$i<$u16($cmap+2);$i++){$p=$cmap+4+$i*8;$off=$cmap+$u32($p+4);if($u16($off)===4&&($u16($p)===3||$u16($p)===0))$format=$off;}if($format===null)throw new \RuntimeException('Шрифт не содержит Unicode cmap');
        $segments=$u16($format+6)/2;$ends=$format+14;$starts=$ends+$segments*2+2;$deltas=$starts+$segments*2;$ranges=$deltas+$segments*2;
        $glyph=function(int $char)use($u16,$font,$segments,$ends,$starts,$deltas,$ranges):int{for($i=0;$i<$segments;$i++)if($char<=$u16($ends+$i*2)&&$char>=$u16($starts+$i*2)){$delta=$u16($deltas+$i*2);$range=$u16($ranges+$i*2);if(!$range)return($char+$delta)&65535;$g=$u16($ranges+$i*2+$range+2*($char-$u16($starts+$i*2)));return$g?($g+$delta)&65535:0;}return 0;};
        $lines=[];foreach($paragraphs as$paragraph){foreach(explode("\n",strip_tags((string)$paragraph))as$line){$words=preg_split('/\s+/u',$line);$part='';foreach($words as$word){if(mb_strlen($part.' '.$word)>84){$lines[]=$part;$part=$word;}else$part.=($part===''?'':' ').$word;}if($part!==''||$line==='')$lines[]=$part;}$lines[]='';}
        $chars=[32=>true];foreach($lines as$line)foreach(mb_str_split($line)as$c){$n=mb_ord($c,'UTF-8');if($n<=65535)$chars[$n]=true;}ksort($chars);$map=str_repeat("\0",(max(array_keys($chars))+1)*2);$width=[];$units=$u16($tables['head']+18);$metrics=$u16($tables['hhea']+34);
        foreach($chars as$code=>$_){$g=$glyph($code);$value=pack('n',$g);$map[$code*2]=$value[0];$map[$code*2+1]=$value[1];$width[]=$code.' ['.(int)round($u16($tables['hmtx']+min($g,$metrics-1)*4)*1000/$units).']';}
        $objects=[];$add=function(string $object)use(&$objects):int{$objects[]=$object;return count($objects);};$stream=fn($bytes,$extra='')=>'<< /Length '.strlen($bytes).' '.$extra.">>\nstream\n".$bytes."\nendstream";
        $catalog=$add('');$pages=$add('');static $compressed=[];$fontKey=$needsFull?'full':'compact';$compressed[$fontKey]??=gzcompress($font);$fontFile=$add($stream($compressed[$fontKey],'/Filter /FlateDecode /Length1 '.strlen($font)));$descriptor=$add('<< /Type /FontDescriptor /FontName /DejaVuSans /Flags 32 /FontBBox [-1100 -500 1900 1300] /ItalicAngle 0 /Ascent 928 /Descent -236 /CapHeight 730 /StemV 80 /FontFile2 '.$fontFile.' 0 R >>');$gid=$add($stream($map));$cid=$add('<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DejaVuSans /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor '.$descriptor.' 0 R /DW 600 /W ['.implode(' ',$width).'] /CIDToGIDMap '.$gid.' 0 R >>');
        $unicode=$add($stream("/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n/CMapName /Identity-UCS def\n/CMapType 2 def\n1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n1 beginbfrange\n<0000> <FFFF> <0000>\nendbfrange\nendcmap\nCMapName currentdict /CMap defineresource pop\nend\nend"));$type0=$add('<< /Type /Font /Subtype /Type0 /BaseFont /DejaVuSans /Encoding /Identity-H /DescendantFonts ['.$cid.' 0 R] /ToUnicode '.$unicode.' 0 R >>');$kids=[];
        foreach(array_chunk($lines,46)as$number=>$chunk){$text="BT /F1 10 Tf 14 TL 48 790 Td\n";foreach($chunk as$line)$text.='<'.bin2hex(mb_convert_encoding($line,'UTF-16BE','UTF-8'))."> Tj T*\n";$text.="ET\nBT /F1 8 Tf 48 35 Td <".bin2hex(mb_convert_encoding('ЧОППРО · Страница '.($number+1),'UTF-16BE','UTF-8'))."> Tj ET";$content=$add($stream($text));$kids[]=$add('<< /Type /Page /Parent '.$pages.' 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 '.$type0.' 0 R >> >> /Contents '.$content.' 0 R >>');}
        $objects[$catalog-1]='<< /Type /Catalog /Pages '.$pages.' 0 R >>';$objects[$pages-1]='<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ',array_map(fn($i)=>$i.' 0 R',$kids)).'] >>';$pdf="%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";$offsets=[0];foreach($objects as$i=>$object){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$object."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".count($offsets)."\n0000000000 65535 f \n";foreach(array_slice($offsets,1)as$offset)$pdf.=sprintf("%010d 00000 n \n",$offset);return$pdf.'trailer << /Size '.count($offsets).' /Root '.$catalog." 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
