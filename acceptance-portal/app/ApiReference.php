<?php
declare(strict_types=1);
final class ApiReference
{
    private static function responseDescription(array $response,array $spec):string
    {
        if(isset($response['description']))return(string)$response['description'];
        $ref=$response['$ref']??'';if(str_starts_with($ref,'#/components/responses/')){
            $key=str_replace(['~1','~0'],['/','~'],substr($ref,23));return(string)($spec['components']['responses'][$key]['description']??'Ответ сервера');
        }
        return'Ответ сервера';
    }
    public static function render(array $spec):string
    {
        $html='<div class="api-intro"><span class="badge">OpenAPI 3.0.3 · '.e($spec['info']['version']??'').'</span><p>'.e($spec['info']['description']??'').'</p></div>';
        foreach($spec['tags']??[]as$tag){$html.='<section class="api-group"><h2>'.e($tag['name']).'</h2>';foreach($spec['paths']??[]as$path=>$operations)foreach($operations as$method=>$op){if(($op['tags'][0]??'')!==$tag['name'])continue;$html.='<details class="api-endpoint"><summary><span class="api-method method-'.e($method).'">'.e(strtoupper($method)).'</span><code>'.e('/v1'.$path).'</code><strong>'.e($op['summary']??'').'</strong></summary><div class="api-operation">';if(!empty($op['description']))$html.='<p>'.e($op['description']).'</p>';if(!empty($op['parameters'])){$html.='<h3>Параметры</h3><dl class="api-parameters">';foreach($op['parameters']as$p)$html.='<dt><code>'.e($p['name']).'</code>'.($p['required']??false?' · обязательный':'').'</dt><dd>'.e($p['description']??'Параметр запроса').'</dd>';$html.='</dl>';}if(!empty($op['requestBody']))$html.='<h3>Тело запроса</h3><pre><code>'.e(json_encode($op['requestBody']['content'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).'</code></pre>';$html.='<h3>Ответы</h3><div class="api-response-list">';foreach($op['responses']as$code=>$r)$html.='<span><b>'.e($code).'</b> '.e(self::responseDescription($r,$spec)).'</span>';$html.='</div></div></details>';}$html.='</section>';}
        $html.='<details class="api-schemas"><summary>Схемы данных</summary><pre><code>'.e(json_encode($spec['components']['schemas']??[],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).'</code></pre></details>';return$html;
    }
}
