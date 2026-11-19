import React, { useEffect, useRef, useState } from 'react';
import { AccessibilityInfo, ActivityIndicator, Animated, Image, Pressable, StyleSheet, Text as NativeText, TextInput, View, type TextProps } from 'react-native';
import Svg, { Path, Circle, Polyline, Rect } from 'react-native-svg';
import { colors, dimensions, fonts, textSize } from './theme';
export { colors } from './theme';

export function Text({style,...props}:TextProps) {
  return <NativeText {...props} style={[{fontFamily:fonts.regular},style]}/>;
}
const paths: Record<string,string> = {
  shield:'M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6z',calendar:'M4 5h16v16H4z M8 3v4 M16 3v4 M4 10h16',qr:'M3 3h6v6H3z M15 3h6v6h-6z M3 15h6v6H3z M15 15h3v3h3v3h-6z',route:'M6 6h12v6H6v6h12 M3 6h1 M20 18h1',alert:'M12 3 2 21h20z M12 9v5 M12 17v1',sync:'M20 7a8 8 0 0 0-14-2 L3 8 M3 3v5h5 M4 17a8 8 0 0 0 14 2l3-3 M21 21v-5h-5',user:'M4 21v-3a8 8 0 0 1 16 0v3 M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8',map:'M9 3 3 5v16l6-2 6 2 6-2V3l-6 2z M9 3v16 M15 5v16',arrow:'M5 12h14 M13 6l6 6-6 6',back:'M19 12H5 M11 6l-6 6 6 6',check:'M4 12l5 5L20 6',file:'M5 3h9l5 5v13H5z M14 3v6h5 M9 13h6 M9 17h6',camera:'M3 7h5l2-3h4l2 3h5v14H3z M12 17a4 4 0 1 0 0-8 4 4 0 0 0 0 8',mic:'M9 3h6v10H9z M5 10v3a7 7 0 0 0 14 0v-3 M12 20v3 M8 23h8',search:'M10 17a7 7 0 1 0 0-14 7 7 0 0 0 0 14 M15 15l6 6',clock:'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18 M12 7v5l4 2',logout:'M9 3H3v18h6 M8 12h13 M17 8l4 4-4 4',point:'M12 22s8-8 8-13a8 8 0 1 0-16 0c0 5 8 13 8 13 M12 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6'};
export function Icon({name,size=22,color=colors.ink}: {name:string;size?:number;color?:string}) { return <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={1.7} strokeLinecap="round" strokeLinejoin="round"><Path d={paths[name] ?? paths.file}/></Svg>; }
export function BrandMark({size=64}: {size?:number}) { return <Image source={require('../../assets/icon.png')} accessibilityLabel="ЧОППРО" style={{width:size,height:size,borderRadius:size*.24}}/>; }
export function Avatar({name,size=62}: {name:string;size?:number}) { const initials=String(name||'Сотрудник').trim().split(/\s+/).slice(0,2).map(part=>part[0]).join('').toLocaleUpperCase('ru-RU');return <View accessibilityLabel={'Профиль: '+name} style={{width:size,height:size,borderRadius:size/2,backgroundColor:colors.navy,alignItems:'center',justifyContent:'center',borderWidth:3,borderColor:colors.line}}><Text style={{fontSize:size*.3,fontFamily:fonts.bold,letterSpacing:1,color:colors.white}}>{initials}</Text></View>; }
export function Label({children,muted=false}: {children:React.ReactNode;muted?:boolean}) { return <Text style={[s.label,muted && {color:colors.muted}]}>{children}</Text>; }
export function Heading({children,small=false}: {children:React.ReactNode;small?:boolean}) { return <Text style={[s.heading,small && {fontSize:textSize.h2}]}>{children}</Text>; }
type FieldProps = {
  label: string;
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  multiline?: boolean;
  keyboardType?: 'default' | 'phone-pad' | 'number-pad' | 'url' | 'email-address';
  testID?: string;
  secure?: boolean;
};

export function Field({label,value,onChange,placeholder='',multiline=false,keyboardType='default',testID,secure=false}:FieldProps) {
  const [focused,setFocused]=useState(false);
  return <View style={s.field}>
    <Text style={s.fieldLabel}>{label}</Text>
    <TextInput accessibilityLabel={label} testID={testID}
      style={[s.input,focused && s.inputFocus,multiline && s.multiline]}
      value={value} onChangeText={onChange} placeholder={placeholder} placeholderTextColor={colors.secondary}
      onFocus={()=>setFocused(true)} onBlur={()=>setFocused(false)}
      multiline={multiline} keyboardType={keyboardType} secureTextEntry={secure} autoCapitalize="none" autoCorrect={false}/>
  </View>;
}

type ButtonProps = {
  children: React.ReactNode;
  onPress: () => void;
  icon?: string;
  secondary?: boolean;
  danger?: boolean;
  disabled?: boolean;
  loading?: boolean;
  testID?: string;
};

export function Button({children,onPress,icon,secondary=false,danger=false,disabled=false,loading=false,testID}:ButtonProps) {
  const [hovered,setHovered]=useState(false);
  const inactive=disabled||loading;
  return <Pressable accessibilityRole="button" accessibilityState={{disabled:inactive,busy:loading}}
    testID={testID} disabled={inactive} onPress={onPress}
    onHoverIn={()=>setHovered(true)} onHoverOut={()=>setHovered(false)}
    style={({pressed})=>{
      const accent=inactive?colors.secondary:danger?colors.red:hovered||pressed?colors.hover:colors.accent;
      return [s.button,secondary && s.secondary,{backgroundColor:secondary?colors.white:accent,borderColor:accent}];
    }}>
    {loading?<ActivityIndicator color={secondary?colors.accent:colors.white}/>:icon && <Icon name={icon} color={secondary?inactive?colors.secondary:colors.accent:colors.white} size={16}/>}
    <Text style={[s.buttonText,secondary && {color:inactive?colors.secondary:hovered?colors.hover:colors.accent}]}>{children}</Text>
  </Pressable>;
}

const tones={teal:colors.info,green:colors.green,amber:colors.amber,red:colors.red,muted:colors.secondary};
export function Badge({children,tone='teal'}: {children:React.ReactNode;tone?:keyof typeof tones}) {
  const color=tones[tone];
  return <View style={[s.badge,{borderColor:color}]}><Text style={[s.badgeText,{color}]}>{children}</Text></View>;
}

export function Card({children,onPress,accent=false}: {children:React.ReactNode;onPress?:()=>void;accent?:boolean}) {
  const style=[s.card,accent && s.cardAccent];
  return onPress?<Pressable accessibilityRole="button" onPress={onPress} style={({pressed})=>[...style,pressed && {backgroundColor:colors.pale}]}>{children}</Pressable>:<View style={style}>{children}</View>;
}
export function Empty({title,text,icon='calendar'}: {title:string;text:string;icon?:string}) { return <View style={s.empty}><View style={s.emptyIcon}><Icon name={icon} size={32} color={colors.accent}/></View><Heading small>{title}</Heading><Label muted>{text}</Label></View>; }
export function Fade({children}: {children:React.ReactNode}) { const opacity = useRef(new Animated.Value(1)).current; useEffect(() => { void AccessibilityInfo.isReduceMotionEnabled().then(reduced => { if (!reduced) { opacity.setValue(0); Animated.timing(opacity,{toValue:1,duration:180,useNativeDriver:true}).start(); } }); },[]); return <Animated.View style={{opacity}}>{children}</Animated.View>; }
export function Progress({value,total}: {value:number;total:number}) { const ratio = total?Math.max(0,Math.min(1,value/total)):0; return <View><View style={s.progress}><View style={{width:`${ratio*100}%`,backgroundColor:colors.accent,height:7,borderRadius:7}}/></View><Text style={s.caption}>{value} из {total} контрольных точек</Text></View>; }
export const s = StyleSheet.create({
  label: {fontSize:textSize.body,lineHeight:21,color:colors.ink},
  heading: {fontSize:textSize.h1,fontFamily:fonts.bold,color:colors.navy,marginBottom:10},
  field: {marginBottom:15},
  fieldLabel: {color:colors.ink,fontSize:textSize.body,fontFamily:fonts.medium,marginBottom:7},
  input: {height:dimensions.field,borderWidth:1,borderColor:colors.navy,borderRadius:dimensions.pill,paddingHorizontal:24,paddingVertical:12,fontSize:textSize.body,fontFamily:fonts.regular,color:colors.navy,backgroundColor:colors.white},
  inputFocus: {borderColor:colors.accent},
  multiline: {height:120,textAlignVertical:'top',borderRadius:24},
  button: {minHeight:dimensions.button,borderWidth:1,borderRadius:dimensions.pill,paddingHorizontal:24,paddingVertical:10,flexDirection:'row',alignItems:'center',justifyContent:'center',gap:8,marginVertical:5},
  secondary: {minHeight:dimensions.outlineButton},
  buttonText: {fontSize:textSize.body,fontFamily:fonts.medium,color:colors.white,textAlign:'center'},
  card: {backgroundColor:colors.white,borderRadius:dimensions.cardRadius,padding:dimensions.cardPadding,marginBottom:14,gap:10},
  cardAccent: {borderLeftWidth:4,borderLeftColor:colors.accent},
  badge: {minHeight:26,paddingHorizontal:15,paddingVertical:4,borderWidth:1,borderRadius:dimensions.pill,alignSelf:'flex-start',justifyContent:'center'},
  badgeText: {fontSize:textSize.caption},
  empty: {alignItems:'center',paddingVertical:36,paddingHorizontal:20,gap:10},
  emptyIcon: {width:62,height:62,backgroundColor:colors.pale,borderRadius:19,alignItems:'center',justifyContent:'center',marginBottom:5},
  progress: {height:7,borderRadius:7,backgroundColor:colors.line,overflow:'hidden',marginVertical:10},
  caption: {color:colors.muted,fontSize:textSize.caption,lineHeight:18},
  row: {flexDirection:'row',alignItems:'center',gap:12},
  between: {flexDirection:'row',alignItems:'center',justifyContent:'space-between',gap:8},
  section: {fontSize:textSize.caption,fontFamily:fonts.semibold,color:colors.muted,marginTop:22,marginBottom:12},
  error: {padding:14,borderRadius:24,backgroundColor:'#FDEDED',marginBottom:12},
  errorText: {color:colors.red,fontSize:textSize.body,lineHeight:21},
});
