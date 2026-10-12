#!/usr/bin/env php
<?php

/**
 * @file
 * Install the template as a new Drupal extension, based on user input.
 *
 * It runs once per fresh copy of the template and deletes itself unless the
 * 'Remove this script' prompt is answered 'no'.
 *
 * Environment variables:
 * - SCRIPT_RUN_SKIP: Set to '1' to skip running of the script. Useful when
 *   unit-testing or requiring this file from other files.
 * - INSTALL_*: Set environment variables to pre-fill prompts
 *   (e.g. INSTALL_NAME, INSTALL_TYPE, INSTALL_DRUPAL_VERSION).
 * - INSTALL_BRANCHES: The branches the shipped workflows run on,
 *   comma-separated. Defaults to the 'push' branches of the committed
 *   '.github/workflows/test.yml', or '1.x'.
 *
 * Usage:
 * @code
 * php install.php
 * php install.php --help
 * @endcode
 */

declare(strict_types=1);

/**
 * The template version, as 'git describe' output.
 *
 * '.gitattributes' marks this file 'export-subst', so 'git archive' expands
 * the placeholder to the tag of the archived commit, or to a commit after a
 * tag, such as '5.0.0-3-g1a2b3c4'. A copy that no archive produced keeps the
 * placeholder. Git expands every placeholder up to the next dollar sign, so
 * no other text in this file may start one.
 */
const TEMPLATE_VERSION = '$Format:%(describe:tags=true)$';

// phpcs:disable
// @embed-start
/**
 * 🧙Prompty - Zero-dependency interactive CLI prompt library.
 *
 * Copy the contents of this file directly into your script, preserving
 * this header.
 *
 * @license MIT
 * @see LICENSE file for full license text.
 *
 * Copyright (c) 2026 Alex Skrypnyk (alex@drevops.com)
 * https://github.com/AlexSkrypnyk/prompty
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software to deal in it without restriction, including the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies, subject to the following condition:
 *
 * This notice must be included in all copies of this file, including when
 * used as a part of other files.
 *
 * @phpstan-type PromptyConfig array{
 *   symbols_unicode: array<string, string>,
 *   symbols_ascii: array<string, string>,
 *   colors: array<string, string>,
 *   spacing: array<string, string>,
 *   labels: array<string, string>,
 *   unicode: bool|null,
 *   ansi: bool|null,
 *   env_prefix: string,
 *   truthy: list<string>,
 *   falsy: list<string>,
 * }
 */
// @phpstan-ignore-next-line
class Prompty{protected static?self $_p0=NULL;protected static bool $_p1=FALSE;protected?string $_p2=NULL;protected array $_p3=[];protected array $_p4=[];protected?array $_p5=NULL;protected $_p6;protected array $_p7=['bar'=>'│','completed'=>'◆','active'=>'◇','intro'=>'┌','outro'=>'└','pointer'=>'❯','radio_on'=>'●','radio_off'=>'○','check_on'=>'◼','check_off'=>'◻','hint_arrow'=>'↳',];protected array $_p8=['bar'=>'|','completed'=>'+','active'=>'o','intro'=>'#','outro'=>'#','pointer'=>'>','radio_on'=>'(*)','radio_off'=>'( )','check_on'=>'[x]','check_off'=>'[ ]','hint_arrow'=>'-->',];protected array $_p9;protected array $_p10=['reset'=>"\033[0m",'dim'=>"\033[2m",'dim_italic'=>"\033[2;3m",'cyan'=>"\033[36m",'green'=>"\033[32m",'red'=>"\033[31m",'gray'=>"\033[90m",'bold'=>"\033[1m",'white'=>"\033[37m",];protected array $_p11;protected array $_p12=['indent'=>'  ','hint_indent'=>'    ','hint_cont'=>'      ',];protected array $_p13=['yes'=>'Yes','no'=>'No','cancelled'=>'(cancelled)','none'=>'None','separator'=>'/',];protected?bool $_p14=NULL;protected?bool $_p15=NULL;protected string $_p16='PROMPTY_';protected array $_p17=['1','true','yes'];protected array $_p18=['0','false','no'];protected function __construct(){if($this->_p14===NULL){$a=getenv('LANG')?:getenv('LC_ALL')?:getenv('LC_CTYPE')?:setlocale(LC_CTYPE,'0')?:'';$this->_p14=stripos($a,'utf')!==FALSE;}$this->_p11=$this->_p10;if($this->_p15===NULL){$b=getenv('NO_COLOR');$this->_p15=($b!==FALSE&&$b!=='')||getenv('TERM')==='dumb'?FALSE:TRUE;}$this->_m1();}protected static function _m0():static{if(!static::$_p0 instanceof Prompty){static::$_p0=new static();}return static::$_p0;}protected function _m1():void{$this->_p9=$this->_p14?$this->_p7:$this->_p8;$this->_p10=$this->_p15?$this->_p11:array_fill_keys(array_keys($this->_p11),'');}protected function _m2():array{return['symbols_unicode'=>$this->_p7,'symbols_ascii'=>$this->_p8,'colors'=>$this->_p11,'spacing'=>$this->_p12,'labels'=>$this->_p13,'unicode'=>$this->_p14,'ansi'=>$this->_p15,'env_prefix'=>$this->_p16,'truthy'=>$this->_p17,'falsy'=>$this->_p18,];}protected function _m3(array $c):void{$this->_p7=$c['symbols_unicode'];$this->_p8=$c['symbols_ascii'];$this->_p11=$c['colors'];$this->_p12=$c['spacing'];$this->_p13=$c['labels'];$this->_p14=$c['unicode'];$this->_p15=$c['ansi'];$this->_p16=$c['env_prefix'];$this->_p17=$c['truthy'];$this->_p18=$c['falsy'];$this->_m1();}public static function config():array{$d=static::_m0();return['symbols_unicode'=>$d->_p7,'symbols_ascii'=>$d->_p8,'symbols'=>$d->_p9,'colors'=>$d->_p10,'spacing'=>$d->_p12,'labels'=>$d->_p13,'unicode'=>$d->_p14,'ansi'=>$d->_p15,'env_prefix'=>$d->_p16,'truthy'=>$d->_p17,'falsy'=>$d->_p18,];}public static function results():array{return static::_m0()->_p3;}public static function version():string{return str_starts_with('0.7.0','__')?'development':'0.7.0';}public static function configure(?array $symbols_unicode=NULL,?array $symbols_ascii=NULL,?array $colors=NULL,?array $spacing=NULL,?array $labels=NULL,?bool $unicode=NULL,?bool $ansi=NULL,?string $env_prefix=NULL,?array $truthy=NULL,?array $falsy=NULL,):void{$d=static::_m0();if($symbols_unicode!==NULL){$d->_m22('symbols_unicode',$symbols_unicode,$d->_p7);}if($symbols_ascii!==NULL){$d->_m22('symbols_ascii',$symbols_ascii,$d->_p8);}if($colors!==NULL){$d->_m22('colors',$colors,$d->_p11);}if($spacing!==NULL){$d->_m22('spacing',$spacing,$d->_p12);}if($labels!==NULL){$d->_m22('labels',$labels,$d->_p13);}if($truthy!==NULL){$d->_m23('truthy',$truthy);}if($falsy!==NULL){$d->_m23('falsy',$falsy);}if($symbols_unicode!==NULL){$d->_p7=array_replace($d->_p7,$symbols_unicode);}if($symbols_ascii!==NULL){$d->_p8=array_replace($d->_p8,$symbols_ascii);}if($colors!==NULL){$d->_p11=array_replace($d->_p11,$colors);$d->_p10=array_replace($d->_p10,$colors);}if($spacing!==NULL){$d->_p12=array_replace($d->_p12,$spacing);}if($labels!==NULL){$d->_p13=array_replace($d->_p13,$labels);}if($unicode!==NULL){$d->_p14=$unicode;}if($ansi!==NULL){$d->_p15=$ansi;}if($env_prefix!==NULL){$d->_p16=$env_prefix;}if($truthy!==NULL){$d->_p17=$truthy;}if($falsy!==NULL){$d->_p18=$falsy;}$d->_m1();}public static function flow(callable $steps,string|callable|null $intro=NULL,string|callable|null $outro=NULL,string|callable|null $cancelled=NULL,bool $numbering=FALSE,?array $symbols_unicode=NULL,?array $symbols_ascii=NULL,?array $colors=NULL,?array $spacing=NULL,?array $labels=NULL,?bool $unicode=NULL,?bool $ansi=NULL,?string $env_prefix=NULL,?array $truthy=NULL,?array $falsy=NULL,):?array{$d=static::_m0();$c=NULL;if($symbols_unicode!==NULL||$symbols_ascii!==NULL||$colors!==NULL||$spacing!==NULL||$labels!==NULL||$unicode!==NULL||$ansi!==NULL||$env_prefix!==NULL||$truthy!==NULL||$falsy!==NULL){$c=$d->_m2();static::configure($symbols_unicode,$symbols_ascii,$colors,$spacing,$labels,$unicode,$ansi,$env_prefix,$truthy,$falsy);}$d->_p3=[];static::$_p1=TRUE;try{$steps=$steps();$d->_m27($steps);$options=['numbering'=>$numbering,];$d->_m14();if($d->_p2!==NULL){register_shutdown_function(function()use($d):void{$d->_m15();});}if($intro!==NULL){is_callable($intro)?$intro($d->_p3):$d->_m16($d->_m29($intro));}$e=$d->_m39($steps,0,$options,'');if($e===FALSE){if($cancelled!==NULL){is_callable($cancelled)?$cancelled($d->_p3):$d->_m16($d->_m30($cancelled));}return NULL;}if($outro!==NULL){is_callable($outro)?$outro($d->_p3):$d->_m16($d->_m30($outro));}return $d->_p3;}finally{$d->_m15();$d->_p5=NULL;static::$_p1=FALSE;if($c!==NULL){$d->_m3($c);}}}public static function text(string $label,string $default='',string $placeholder='',string $description='',mixed $discovered=NULL,?callable $condition=NULL,array $children=[],?array $ctx=NULL,):\Closure|array|string|null{if(static::$_p1&&$ctx===NULL){$f=fn(array $ctx):\Closure|array|string|null=>static::text($label,default:$default,placeholder:$placeholder,description:$description,discovered:$discovered,ctx:$ctx,);if($condition!==NULL||$children!==[]){return['__call'=>$f,'__children'=>$children,'__condition'=>$condition];}return $f;}$d=static::_m0();$ctx??=['depth'=>0,'is_last'=>FALSE,'open'=>[],];$g=!static::$_p1;if($g){$d->_m14();}$h=$ctx['depth']??0;$_p4=$ctx['open']??[];$label=$d->_m20($label,$ctx);$default=$d->_m5($default);$placeholder=$d->_m5($placeholder);$i=$discovered??$ctx['discovered']??NULL;if($i!==NULL){$j=trim($d->_m5((string)$i));if($j===''){$j=$default!==''?$default:$placeholder;}$_p4=$d->_m38($j,$_p4);$d->_m16($d->_m33($label,$j,$h,$_p4));if($g){$d->_m15();}return $j;}$k=function(string $l)use($d,$label,$placeholder,$description,$h,$_p4):array{$l=$d->_m5($l);$m=$d->_m4('█','cyan');$j=$l===''?$d->_m4($placeholder,'gray').$m:$d->_m4($l,'white').$m;if($h===0){$lines=[$d->_m4($d->_p9['active'],'cyan').$d->_p12['indent'].$label];$lines=array_merge($lines,$description!==''?$d->_m31($description):[$d->_m7()]);$lines[]=$d->_m7().$d->_p12['indent'].$j;$lines[]=$d->_m7();return $lines;}$n=$d->_m7().$d->_m18($h,$_p4);$o=$d->_m19($h,$_p4);$lines=[$n.$d->_m4($d->_p9['active'],'cyan').$d->_p12['indent'].$label];$lines=array_merge($lines,$description!==''?$d->_m31($description,$h,$_p4):[$d->_m7().$o]);$lines[]=$d->_m7().$o.$j;$lines[]=$d->_m7().$o;return $lines;};$l=$default;$p=$d->_m16($k($l));while(TRUE){$q=$d->_m9();if($d->_m10($q)){$d->_m17($p,$d->_m34($label,$l,$h,$_p4));if($g){$d->_m15();}return NULL;}if($q==='enter'){$l=$d->_m5($l);$j=$l!==''?$l:$placeholder;$_p4=$d->_m38($j,$_p4);$d->_m17($p,$d->_m33($label,$j,$h,$_p4));if($g){$d->_m15();}return $j;}if($q==='backspace'){if($l!==''){$l=mb_substr($l,0,-1);}}elseif($q==='space'){$l.=' ';}elseif(mb_strlen($q)===1&&ord($q)>=32){$l.=$q;}$p=$d->_m17($p,$k($l));}}public static function select(string $label,array $options=[],string $default='',string $description='',array $hints=[],mixed $discovered=NULL,?callable $condition=NULL,array $children=[],?array $ctx=NULL,):\Closure|array|string|null{if(static::$_p1&&$ctx===NULL){$f=fn(array $ctx):\Closure|array|string|null=>static::select($label,options:$options,default:$default,description:$description,hints:$hints,discovered:$discovered,ctx:$ctx,);if($condition!==NULL||$children!==[]){return['__call'=>$f,'__children'=>$children,'__condition'=>$condition];}return $f;}$d=static::_m0();$ctx??=['depth'=>0,'is_last'=>FALSE,'open'=>[],];$g=!static::$_p1;$d->_m24($label,$options);$r=$default===''?NULL:$d->_m25('Default',$label,$default,$options);$i=$discovered??$ctx['discovered']??NULL;$s=$i===NULL?NULL:$d->_m25('Discovered',$label,$i,$options);if($g){$d->_m14();}$h=$ctx['depth']??0;$_p4=$ctx['open']??[];$label=$d->_m20($label,$ctx);$t=$d->_m21($options);$u=array_map($d->_m5(...),array_values($options));$v=array_map(fn(string $q)=>$hints[$q]??'',$t);if($s!==NULL){$_p4=$d->_m38($s,$_p4);$d->_m16($d->_m33($label,$options[$s],$h,$_p4));if($g){$d->_m15();}return $s;}$k=function(int $w)use($d,$label,$u,$description,$v,$h,$_p4):array{if($h===0){$lines=[$d->_m4($d->_p9['active'],'cyan').$d->_p12['indent'].$label];$lines=array_merge($lines,$description!==''?$d->_m31($description):[$d->_m7()]);foreach($u as $x=>$y){$z=$x===$w;$aa=$d->_m4($d->_p9[$z?'radio_on':'radio_off'],$z?'green':'dim');$ab=$z?$y:$d->_m4($y,'dim');$lines[]=$d->_m7().$d->_m8($z).$aa.' '.$ab;if($z&&($v[$x]??'')!==''){$lines=array_merge($lines,$d->_m32($v[$x]));}}$lines[]=$d->_m7();return $lines;}$n=$d->_m7().$d->_m18($h,$_p4);$o=$d->_m19($h,$_p4);$lines=[$n.$d->_m4($d->_p9['active'],'cyan').$d->_p12['indent'].$label];$lines=array_merge($lines,$description!==''?$d->_m31($description,$h,$_p4):[$d->_m7().$o]);foreach($u as $x=>$y){$z=$x===$w;$aa=$d->_m4($d->_p9[$z?'radio_on':'radio_off'],$z?'green':'dim');$ab=$z?$y:$d->_m4($y,'dim');$lines[]=$d->_m7().$o.$d->_m8($z).$aa.' '.$ab;if($z&&($v[$x]??'')!==''){$lines=array_merge($lines,$d->_m32($v[$x],$h,$_p4));}}$lines[]=$d->_m7().$o;return $lines;};$w=0;if($r!==NULL){$w=(int)array_search($r,$t,TRUE);}$p=$d->_m16($k($w));while(TRUE){$q=$d->_m9();if($d->_m10($q)){$d->_m17($p,$d->_m34($label,$u[$w],$h,$_p4));if($g){$d->_m15();}return NULL;}if($q==='enter'){$_p4=$d->_m38($t[$w],$_p4);$d->_m17($p,$d->_m33($label,$u[$w],$h,$_p4));if($g){$d->_m15();}return $t[$w];}if($q==='up'||$q==='left'){$w=($w-1+count($u))%count($u);}elseif($q==='down'||$q==='right'){$w=($w+1)%count($u);}$p=$d->_m17($p,$k($w));}}public static function multiselect(string $label,array $options=[],array $default=[],string $description='',array $hints=[],mixed $discovered=NULL,?callable $condition=NULL,array $children=[],?array $ctx=NULL,):\Closure|array|null{if(static::$_p1&&$ctx===NULL){$f=fn(array $ctx):\Closure|array|null=>static::multiselect($label,options:$options,default:$default,description:$description,hints:$hints,discovered:$discovered,ctx:$ctx,);if($condition!==NULL||$children!==[]){return['__call'=>$f,'__children'=>$children,'__condition'=>$condition];}return $f;}$d=static::_m0();$ctx??=['depth'=>0,'is_last'=>FALSE,'open'=>[],];$g=!static::$_p1;$i=$discovered??$ctx['discovered']??NULL;if(is_scalar($i)){$i=explode(',',(string)$i);}$d->_m24($label,$options);$ac=$d->_m26('Default',$label,$default,$options);$ad=$i===NULL?NULL:$d->_m26('Discovered',$label,$i,$options);if($g){$d->_m14();}$h=$ctx['depth']??0;$_p4=$ctx['open']??[];$label=$d->_m20($label,$ctx);$t=$d->_m21($options);$u=array_map($d->_m5(...),array_values($options));$v=array_map(fn(string $q)=>$hints[$q]??'',$t);if($ad!==NULL){$j=$ad!==[]?implode(', ',array_map(fn(string $q):string=>$options[$q],$ad)):$d->_m6('none');$_p4=$d->_m38($ad,$_p4);$d->_m16($d->_m33($label,$j,$h,$_p4));if($g){$d->_m15();}return $ad;}$k=function(int $w,array $ae)use($d,$label,$u,$description,$v,$h,$_p4):array{if($h===0){$lines=[$d->_m4($d->_p9['active'],'cyan').$d->_p12['indent'].$label];$lines=array_merge($lines,$description!==''?$d->_m31($description):[$d->_m7()]);foreach($u as $x=>$y){$af=$ae[$x]??FALSE;$z=$x===$w;$ag=$d->_m4($d->_p9[$af?'check_on':'check_off'],$z||$af?'green':'dim');$ab=$z||$af?$y:$d->_m4($y,'dim');$lines[]=$d->_m7().$d->_m8($z).$ag.' '.$ab;if($z&&($v[$x]??'')!==''){$lines=array_merge($lines,$d->_m32($v[$x]));}}$lines[]=$d->_m7();return $lines;}$n=$d->_m7().$d->_m18($h,$_p4);$o=$d->_m19($h,$_p4);$lines=[$n.$d->_m4($d->_p9['active'],'cyan').$d->_p12['indent'].$label];$lines=array_merge($lines,$description!==''?$d->_m31($description,$h,$_p4):[$d->_m7().$o]);foreach($u as $x=>$y){$af=$ae[$x]??FALSE;$z=$x===$w;$ag=$d->_m4($d->_p9[$af?'check_on':'check_off'],$z||$af?'green':'dim');$ab=$z||$af?$y:$d->_m4($y,'dim');$lines[]=$d->_m7().$o.$d->_m8($z).$ag.' '.$ab;if($z&&($v[$x]??'')!==''){$lines=array_merge($lines,$d->_m32($v[$x],$h,$_p4));}}$lines[]=$d->_m7().$o;return $lines;};$w=0;$ae=array_map(fn(string $q):bool=>in_array($q,$ac,TRUE),$t);$p=$d->_m16($k($w,$ae));while(TRUE){$q=$d->_m9();if($d->_m10($q)){$d->_m17($p,$d->_m34($label,'',$h,$_p4));if($g){$d->_m15();}return NULL;}if($q==='enter'){$ah=[];$ai=[];foreach($u as $x=>$aj){if($ae[$x]){$ah[]=$t[$x];$ai[]=$aj;}}$_p4=$d->_m38($ah,$_p4);$d->_m17($p,$d->_m33($label,$ai!==[]?implode(', ',$ai):$d->_m6('none'),$h,$_p4));if($g){$d->_m15();}return $ah;}if($q==='space'){$ae[$w]=!$ae[$w];}elseif($q==='up'||$q==='left'){$w=($w-1+count($u))%count($u);}elseif($q==='down'||$q==='right'){$w=($w+1)%count($u);}$p=$d->_m17($p,$k($w,$ae));}}public static function confirm(string $label,bool $default=TRUE,string $description='',mixed $discovered=NULL,?callable $condition=NULL,array $children=[],?array $ctx=NULL,):\Closure|array|bool|null{if(static::$_p1&&$ctx===NULL){$f=fn(array $ctx):\Closure|array|bool|null=>static::confirm($label,default:$default,description:$description,discovered:$discovered,ctx:$ctx,);if($condition!==NULL||$children!==[]){return['__call'=>$f,'__children'=>$children,'__condition'=>$condition];}return $f;}$d=static::_m0();$ctx??=['depth'=>0,'is_last'=>FALSE,'open'=>[],];$g=!static::$_p1;$truthy=$ctx['truthy']??$d->_p17;$falsy=$ctx['falsy']??$d->_p18;$i=$discovered??$ctx['discovered']??NULL;$ak=$i===NULL?NULL:$d->_m28($label,$i,$truthy,$falsy);if($g){$d->_m14();}$h=$ctx['depth']??0;$_p4=$ctx['open']??[];$label=$d->_m20($label,$ctx);if($ak!==NULL){$_p4=$d->_m38($ak,$_p4);$d->_m16($d->_m33($label,$ak?$d->_m6('yes'):$d->_m6('no'),$h,$_p4));if($g){$d->_m15();}return $ak;}$k=function(bool $al)use($d,$label,$description,$h,$_p4):array{$am=$al?$d->_m4($d->_p9['radio_on'],'green').' '.$d->_m6('yes').' '.$d->_m4($d->_m6('separator'),'dim').' '.$d->_m4($d->_p9['radio_off'],'dim').' '.$d->_m4($d->_m6('no'),'dim'):$d->_m4($d->_p9['radio_off'],'dim').' '.$d->_m4($d->_m6('yes'),'dim').' '.$d->_m4($d->_m6('separator'),'dim').' '.$d->_m4($d->_p9['radio_on'],'green').' '.$d->_m6('no');if($h===0){$lines=[$d->_m4($d->_p9['active'],'cyan').$d->_p12['indent'].$label];$lines=array_merge($lines,$description!==''?$d->_m31($description):[$d->_m7()]);$lines[]=$d->_m7().$d->_p12['indent'].$am;$lines[]=$d->_m7();return $lines;}$n=$d->_m7().$d->_m18($h,$_p4);$o=$d->_m19($h,$_p4);$lines=[$n.$d->_m4($d->_p9['active'],'cyan').$d->_p12['indent'].$label];$lines=array_merge($lines,$description!==''?$d->_m31($description,$h,$_p4):[$d->_m7().$o]);$lines[]=$d->_m7().$o.$am;$lines[]=$d->_m7().$o;return $lines;};$an=$default;$p=$d->_m16($k($an));while(TRUE){$q=$d->_m9();if($d->_m10($q)){$d->_m17($p,$d->_m34($label,$an?$d->_m6('yes'):$d->_m6('no'),$h,$_p4));if($g){$d->_m15();}return NULL;}if($q==='enter'){$_p4=$d->_m38($an,$_p4);$d->_m17($p,$d->_m33($label,$an?$d->_m6('yes'):$d->_m6('no'),$h,$_p4));if($g){$d->_m15();}return $an;}if(in_array($q,['left','right','up','down','tab'],TRUE)){$an=!$an;}elseif($q==='y'||$q==='Y'){$an=TRUE;}elseif($q==='n'||$q==='N'){$an=FALSE;}$p=$d->_m17($p,$k($an));}}public static function intro(string $message):void{$d=static::_m0();$d->_m16($d->_m29($message));}public static function outro(string $message):void{$d=static::_m0();$d->_m16($d->_m30($message));}public static function output(array $lines):int{return static::_m0()->_m16($lines);}protected function _m4(string $ab,string $ao):string{return isset($this->_p10[$ao])?$this->_p10[$ao].$ab.$this->_p10['reset']:$ab;}protected function _m5(string $ab):string{$ab=preg_replace('/\033\[\??[0-9;]*[A-Za-z]/','',$ab)??$ab;$ab=preg_replace('/[\t\n\x0b\x0c\r]+/',' ',$ab)??$ab;$ab=preg_replace('/[\x00-\x1f\x7f]/','',$ab)??$ab;return preg_replace('/\p{Cc}/u','',$ab)??$ab;}protected function _m6(string $ap):string{return $this->_m5($this->_p13[$ap]??'');}protected function _m7():string{return $this->_m4($this->_p9['bar'],'gray');}protected function _m8(bool $w):string{return($w?$this->_m4($this->_p9['pointer'],'cyan'):' ').' ';}protected function _m9():string{$aq=$this->_p6??STDIN;$ar=fread($aq,1);if($ar===FALSE||$ar===''){return'eof';}return match($ar){"\x03"=>'ctrl-c',"\n","\r"=>'enter',"\x7f","\x08"=>'backspace',"\t"=>'tab',' '=>'space',"\x1b"=>match(fread($aq,2)){'[A'=>'up','[B'=>'down','[C'=>'right','[D'=>'left',default=>'escape',},default=>$ar,};}protected function _m10(string $q):bool{return in_array($q,['ctrl-c','escape','eof'],TRUE);}protected function _m11():void{echo"\033[?25h";}protected function _m12():void{echo"\033[?25l";}protected function _m13(string $as):void{shell_exec('stty '.$as.' 2>/dev/null');}protected function _m14():void{$this->_p2=$this->_p6===NULL?(shell_exec('stty -g 2>/dev/null')?:NULL):NULL;if($this->_p2!==NULL){$this->_p2=trim($this->_p2);shell_exec('stty -echo -icanon min 1 time 0 2>/dev/null');$this->_m12();}}protected function _m15():void{if($this->_p2!==NULL){$this->_m13($this->_p2);$this->_m11();$this->_p2=NULL;}}protected function _m16(array $lines):int{echo implode(PHP_EOL,$lines).PHP_EOL;return count($lines);}protected function _m17(int $at,array $lines):int{if($at>0){echo"\033[{$at}A\r\033[J";}return $this->_m16($lines);}protected function _m18(int $h,array $_p4):string{$au='  ';for($av=1;$av<$h;$av++){$au.=($_p4[$av]??FALSE)?$this->_m4($this->_p9['bar'],'gray').'  ':'   ';}return $au;}protected function _m19(int $h,array $_p4):string{$au='  ';for($av=1;$av<=$h;$av++){$au.=($_p4[$av]??FALSE)?$this->_m4($this->_p9['bar'],'gray').'  ':'   ';}return $au;}protected function _m20(string $label,array $ctx):string{$label=$this->_m5($label);if(isset($ctx['number'])){$aw=$ctx['number'];return $label.' '.$this->_m4('('.$aw.')','dim');}return $label;}protected function _m21(array $options):array{return array_map(strval(...),array_keys($options));}protected function _m22(string $ax,array $ay,array $az):void{foreach(array_keys($ay)as $q){if(!array_key_exists($q,$az)){throw new \InvalidArgumentException(sprintf('Configuration key "%s" for "%s" is not valid. Available keys: %s.',$q,$ax,implode(', ',array_keys($az))));}}}protected function _m23(string $ax,array $ay):void{if($ay===[]){throw new \InvalidArgumentException(sprintf('No values declared for "%s". Provide at least one value.',$ax));}foreach($ay as $l){if(trim($l)===''){throw new \InvalidArgumentException(sprintf('Blank value declared for "%s". Every value must hold a non-space character.',$ax));}}}protected function _m24(string $label,array $options):void{if($options===[]){throw new \InvalidArgumentException(sprintf('No options declared for "%s". Provide at least one option.',$label));}}protected function _m25(string $ax,string $label,mixed $l,array $options):string{$q=is_scalar($l)?trim((string)$l):get_debug_type($l);if(!array_key_exists($q,$options)){$ba=$options===[]?'none':implode(', ',$this->_m21($options));throw new \InvalidArgumentException(sprintf('%s value "%s" for "%s" is not a valid option. Available options: %s.',$ax,$q,$label,$ba));}return $q;}protected function _m26(string $ax,string $label,mixed $l,array $options):array{$bb=is_array($l)?$l:[$l];$bc=[];foreach($bb as $bd){if(is_scalar($bd)&&trim((string)$bd)===''){continue;}$bc[]=$this->_m25($ax,$label,$bd,$options);}return array_values(array_filter($this->_m21($options),fn(string $q):bool=>in_array($q,$bc,TRUE)));}protected function _m27(array $steps):void{foreach($steps as $q=>$be){if((string)$q===''){throw new \InvalidArgumentException('A step must have a key: it names the answer in the results and the variable the answer can come from.');}$ap=$this->_p16.strtoupper((string)$q);if(preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/',$ap)!==1){throw new \InvalidArgumentException(sprintf('Step "%s" is looked up as the environment variable "%s", which cannot be exported. A variable name may hold only letters, digits and underscores, and may not start with a digit.',$q,$ap));}$children=is_array($be)?$be['__children']??NULL:NULL;if(is_array($children)){$this->_m27($children);}}}protected function _m28(string $label,mixed $l,array $truthy,array $falsy):bool{if(is_bool($l)){return $l;}$j=is_scalar($l)?trim((string)$l):get_debug_type($l);$bf=fn(string $bg):string=>strtolower(trim($bg));$bh=$bf($j);if(in_array($bh,array_map($bf,$truthy),TRUE)){return TRUE;}if(in_array($bh,array_map($bf,$falsy),TRUE)){return FALSE;}$bi=implode(', ',array_merge($truthy,$falsy));throw new \InvalidArgumentException(sprintf('Discovered value "%s" for "%s" is not a valid answer. Accepted values: %s.',$j,$label,$bi));}protected function _m29(string $message):array{$message=$this->_m5($message);return['',$this->_m4($this->_p9['intro'],'gray').$this->_p12['indent'].$this->_m4($message,'bold'),$this->_m7(),];}protected function _m30(string $message):array{$message=$this->_m5($message);return[$this->_m7(),$this->_m4($this->_p9['outro'],'gray').$this->_p12['indent'].$this->_m4($message,'green'),'',];}protected function _m31(string $description,int $h=0,array $_p4=[]):array{$o=$h>0?$this->_m19($h,$_p4):$this->_p12['indent'];$lines=array_map(fn(string $bj):string=>$this->_m7().$o.$this->_m4($this->_m5($bj),'dim_italic'),explode("\n",$description),);$lines[]=$this->_m7().($h>0?$this->_m19($h,$_p4):'');return $lines;}protected function _m32(string $bk,int $h=0,array $_p4=[]):array{$o=$h>0?$this->_m19($h,$_p4):'';$bl=array_map($this->_m5(...),explode("\n",$bk));return array_map(fn(string $bj,int $x):string=>$this->_m7().$o.($x===0?$this->_p12['hint_indent'].$this->_m4($this->_p9['hint_arrow'],'dim').' '.$this->_m4($bj,'dim_italic'):$this->_p12['hint_cont'].$this->_m4($bj,'dim_italic')),$bl,array_keys($bl),);}protected function _m33(string $label,string $l,int $h=0,array $_p4=[]):array{$label=$this->_m5($label);$l=$this->_m5($l);if($h===0){return[$this->_m4($this->_p9['completed'],'cyan').$this->_p12['indent'].$label,$this->_m7().$this->_p12['indent'].$this->_m4($l,'dim'),$this->_m7(),];}$n=$this->_m7().$this->_m18($h,$_p4);$o=$this->_m19($h,$_p4);return[$n.$this->_m4($this->_p9['completed'],'cyan').$this->_p12['indent'].$label,$this->_m7().$o.$this->_m4($l,'dim'),$this->_m7().$o,];}protected function _m34(string $label,string $l,int $h=0,array $_p4=[]):array{$label=$this->_m5($label);$l=$this->_m5($l);if($h===0){return[$this->_m4($this->_p9['active'],'red').$this->_p12['indent'].$label,$this->_m7().$this->_p12['indent'].$this->_m4($l,'dim').$this->_m4(' '.$this->_m6('cancelled'),'red'),$this->_m7(),];}$n=$this->_m7().$this->_m18($h,$_p4);$o=$this->_m19($h,$_p4);return[$n.$this->_m4($this->_p9['active'],'red').$this->_p12['indent'].$label,$this->_m7().$o.$this->_m4($l,'dim').$this->_m4(' '.$this->_m6('cancelled'),'red'),$this->_m7().$o,];}protected function _m35(mixed $be):?callable{if(is_callable($be)||!is_array($be)){return NULL;}$condition=$be['__condition']??NULL;return is_callable($condition)?$condition:NULL;}protected function _m36(array $steps,array $_p3):bool{foreach($steps as $be){$condition=$this->_m35($be);if($condition===NULL||$condition($_p3)){return TRUE;}}return FALSE;}protected function _m37(int $h,bool $bm):void{if($h<1){return;}if($bm){unset($this->_p4[$h]);return;}$this->_p4[$h]=TRUE;}protected function _m38(mixed $l,array $_p4):array{$_p5=$this->_p5;if($_p5===NULL){return $_p4;}$_p3=$this->_p3;$_p3[(string)$_p5['key']]=$l;$this->_m37($_p5['depth'],!$this->_m36($_p5['rest'],$_p3));return $this->_p4;}protected function _m39(array $steps,int $h,array $options,string $bn):bool{$bo=0;$x=0;foreach($steps as $q=>$be){$x++;if(is_callable($be)){$f=$be;$children=[];}else{$f=$be['__call'];$children=is_array($be['__children']??NULL)?$be['__children']:[];}$condition=$this->_m35($be);if($condition!==NULL&&!$condition($this->_p3)){continue;}$bo++;$bp=array_slice($steps,$x);$this->_m37($h,$bp===[]);$this->_p5=['key'=>$q,'depth'=>$h,'rest'=>$bp];$aw=$bn!==''?$bn.'.'.$bo:(string)$bo;$bq=getenv($this->_p16.strtoupper((string)$q));$ctx=['depth'=>$h,'is_last'=>$bp===[],'open'=>$this->_p4,'results'=>$this->_p3,'number'=>($options['numbering']??FALSE)?$aw:NULL,'discovered'=>$bq!==FALSE?$bq:NULL,'truthy'=>$this->_p17,'falsy'=>$this->_p18,];$l=$f($ctx);if($l===NULL){$this->_p5=NULL;return FALSE;}$this->_p3[$q]=$l;$this->_m38($l,$this->_p4);$this->_p5=NULL;if($this->_m36($children,$this->_p3)){$br=$h+1;$bs=$this->_m7().$this->_m18($br,$this->_p4).$this->_m7();$this->_m16([$bs]);if(!$this->_m39($children,$br,$options,$aw)){return FALSE;}}}return TRUE;}}
// @embed-end
// phpcs:enable

/**
 * Main functionality.
 *
 * @param array<string> $argv
 *   Array of arguments.
 */
function main(array $argv): void {
  if (array_intersect(['help', '--help', '-h', '-?'], $argv)) {
    print_help();

    return;
  }

  // The interactive flow uses Prompty, which manipulates terminal state
  // (stty, ANSI escapes, shutdown handlers) when attached to a TTY and is
  // not suited to in-process unit testing. The functional 'InstallTest'
  // exercises this path end-to-end via a subprocess.
  // @codeCoverageIgnoreStart
  $branches = parse_branch_list((string) getenv('INSTALL_BRANCHES'));

  // The selectable development tools, all enabled by default.
  $tool_options = [
    'phpcs' => 'PHPCS',
    'phpstan' => 'PHPStan',
    'rector' => 'Rector',
    'twigcs' => 'Twig CS Fixer',
    'eslint' => 'ESLint',
    'stylelint' => 'Stylelint',
    'cspell' => 'CSpell',
    'jest' => 'Jest',
    'phpunit' => 'PHPUnit',
    'functional_javascript' => 'FunctionalJavascript tests',
    'renovate' => 'Renovate',
  ];

  $results = Prompty::flow(
    fn(): array => [
      'name' => Prompty::text('Extension name', placeholder: 'My Extension'),
      'machine_name' => Prompty::text('Machine name', placeholder: 'my_extension'),
      'type' => Prompty::select('Extension type', options: [
        'module' => 'Module',
        'theme' => 'Theme',
      ]),
      'drupal_version' => Prompty::multiselect(
        'Target Drupal versions',
        options: drupal_version_options(),
        default: drupal_version_default(),
        description: 'CI runs against every selected major. Check any other major you also support.',
      ),
      'command_wrapper' => Prompty::multiselect('Command wrapper', options: [
        'ahoy' => 'Ahoy',
        'makefile' => 'Makefile',
      ]),
      'tools' => Prompty::multiselect(
        'Tools',
        options: $tool_options,
        default: array_keys($tool_options),
        description: 'All tools are included by default. Uncheck any to remove from your project.',
      ),
      'examples' => Prompty::confirm(
        'Keep example lifecycle scripts',
        default: FALSE,
        description: 'Sample hooks that only print a marker line when each build phase runs. Keep them as a starting point for your own scripts.',
      ),
      'remove_self' => Prompty::confirm('Remove this script'),
      'proceed' => Prompty::confirm('Proceed with installation'),
    ],
    intro: 'Eddy - Drupal extension scaffold',
    outro: fn(array $r): string => sprintf(
      "Name: %s\nMachine name: %s\nType: %s\nDrupal: %s\nWrapper: %s\nRemoved tools: %s",
      $r['name'],
      $r['machine_name'],
      $r['type'],
      implode(', ', $r['drupal_version'] ?: ['None']),
      implode(', ', $r['command_wrapper'] ?: ['None']),
      implode(', ', array_diff(array_keys($tool_options), array_filter((array) $r['tools'], static fn($v): bool => $v !== '')) ?: ['None']),
    ),
    cancelled: 'Cancelled.',
    numbering: TRUE,
    env_prefix: 'INSTALL_',
  );

  if ($results === NULL || !($results['proceed'] ?? FALSE)) {
    throw new \Exception('Aborting.');
  }

  $name = (string) $results['name'];
  $machine_name = (string) $results['machine_name'];
  $type = (string) $results['type'];
  /** @var array<string> $drupal_versions */
  $drupal_versions = array_filter((array) $results['drupal_version'], static fn($v): bool => $v !== '');
  /** @var array<string> $command_wrapper */
  $command_wrapper = array_filter((array) $results['command_wrapper'], static fn($v): bool => $v !== '');
  /** @var array<string> $tools_keep */
  $tools_keep = array_filter((array) $results['tools'], static fn($v): bool => $v !== '');
  $tools_remove = array_values(array_diff(array_keys($tool_options), $tools_keep));
  // The prompt asks whether to keep the example scripts, so a 'no' answer is
  // what triggers their removal.
  $remove_examples = !($results['examples'] ?? FALSE);
  $remove_self = $results['remove_self'] ?? FALSE;

  // Derive machine name from extension name when the placeholder was kept
  // or the machine name was left empty.
  if ($machine_name === 'my_extension' || $machine_name === '') {
    $machine_name = convert_string($name, 'file_name');
  }

  process($name, $machine_name, $type, $drupal_versions, $command_wrapper, $tools_remove, $remove_examples, $remove_self, $branches, template_version(TEMPLATE_VERSION));
  // @codeCoverageIgnoreEnd
}

/**
 * Define the selectable Drupal major versions.
 *
 * The canonical list of supported majors, used both to build the
 * installation prompt and to prune deselected majors in 'process()'.
 *
 * Extending support takes a new entry here and '#;< DRUPAL_<major>' blocks
 * wherever the existing majors have them. '.eddy/CLAUDE.md' lists every step.
 *
 * @return non-empty-array<int, string>
 *   Map of major version to its human-readable label. PHP casts the
 *   numeric-string keys to integers.
 */
function drupal_version_options(): array {
  return [
    '10' => 'Drupal 10',
    '11' => 'Drupal 11',
    '12' => 'Drupal 12',
  ];
}

/**
 * Define the Drupal majors pre-selected in the installation prompt.
 *
 * A new extension targets current Drupal, so only the newest stable major
 * starts checked; older majors and a pre-release major are opted into. The
 * shipped 'composer.dev.json' sets the build default to the highest of these
 * majors.
 *
 * @return non-empty-array<int, string>
 *   The majors to start checked.
 */
function drupal_version_default(): array {
  return ['11'];
}

/**
 * Print help.
 */
function print_help(): void {
  $script_name = basename(__FILE__);
  $out = <<<EOF
Eddy - project installation.
----------------------------

Usage:
  php {$script_name}

Options:
  --help                This help.

Environment variables (to pre-fill prompts):
  INSTALL_NAME            Extension name.
  INSTALL_MACHINE_NAME    Extension machine name.
  INSTALL_TYPE            Extension type: module or theme.
  INSTALL_DRUPAL_VERSION  Target Drupal majors: comma-separated (e.g. 11).
                          Drupal 11 is targeted by default; CI runs against
                          every selected major. One or more of: 10, 11, 12.
  INSTALL_COMMAND_WRAPPER Command wrapper: ahoy, makefile, or both
                          (comma-separated).
  INSTALL_TOOLS           Tools to keep: comma-separated. All are kept by
                          default; list only the ones to keep to drop the rest.
                          One or more of: phpcs, phpstan, rector, twigcs,
                          eslint, stylelint, cspell, jest, phpunit,
                          functional_javascript, renovate.
  INSTALL_EXAMPLES        Keep example lifecycle scripts: true or false. They
                          are removed by default.
  INSTALL_REMOVE_SELF     Remove this script: true or false.
  INSTALL_PROCEED         Proceed with installation: true or false.

Other environment variables:
  INSTALL_BRANCHES        Branches the workflows run on: comma-separated
                          (e.g. 1.x,2.x). Defaults to the push branches of the
                          committed .github/workflows/test.yml, or 1.x.

EOF;
  print $out;
}

/**
 * Process the project installation.
 *
 * @param string $extension_name
 *   The human-readable extension name.
 * @param string $extension_machine_name
 *   The machine name of the extension.
 * @param string $extension_type
 *   The extension type (module or theme).
 * @param array<string> $drupal_versions
 *   The selected Drupal major versions to target (e.g. '10', '11').
 * @param array<string> $command_wrapper
 *   The selected command wrappers ('ahoy', 'makefile', or both).
 * @param array<string> $tools_remove
 *   The machine names of the development tools to remove.
 * @param bool $remove_examples
 *   Whether to remove the example lifecycle scripts.
 * @param bool $remove_self
 *   Whether to remove this script.
 * @param array<int, string> $branches
 *   The branches the shipped workflows run on, or an empty array to read them
 *   from the committed workflow.
 * @param string|null $version
 *   The template version to record, or NULL to record none.
 */
function process(string $extension_name, string $extension_machine_name, string $extension_type, array $drupal_versions, array $command_wrapper, array $tools_remove, bool $remove_examples, bool $remove_self, array $branches = [], ?string $version = NULL): void {
  // Validate required values.
  if ($extension_name === '') {
    throw new \Exception('Name is required.');
  }

  if ($extension_machine_name === '') {
    throw new \Exception('Machine name is required.');
  }

  if (!preg_match('/^[a-z][a-z0-9_]*$/', $extension_machine_name)) {
    throw new \Exception('Machine name must start with a lowercase letter and contain only lowercase letters, digits, and underscores.');
  }

  if ($extension_type === '') {
    throw new \Exception('Type is required.');
  }

  if ($drupal_versions === []) {
    throw new \Exception('At least one Drupal version is required.');
  }

  // Normalize both sides to strings: PHP casts numeric-string array keys to
  // integers, so 'array_keys()' returns ints that would never strictly match
  // the string selection.
  $supported_majors = array_map(strval(...), array_keys(drupal_version_options()));
  $selected_majors = array_map(strval(...), $drupal_versions);
  $unsupported_majors = array_diff($selected_majors, $supported_majors);

  if ($unsupported_majors !== []) {
    throw new \Exception(sprintf('Unsupported Drupal version: %s.', implode(', ', $unsupported_majors)));
  }

  // Strip the '#;< DRUPAL_<major>' blocks of deselected majors from every
  // project file. Markers for kept majors are cleared later by
  // 'remove_special_comments()'.
  foreach ($supported_majors as $major) {
    if (!in_array($major, $selected_majors, TRUE)) {
      remove_tokens_with_content('DRUPAL_' . $major);
    }
  }

  process_drupal_version_text($selected_majors);

  // A local build defaults to the highest selected major.
  set_composer_dev_eddy_value('drupal-version', (string) max(array_map(intval(...), $selected_majors)));

  if ($version !== NULL) {
    set_composer_dev_eddy_value('version', $version);
  }

  // Remove unwanted command wrappers and their wrapper-specific documentation
  // blocks (marked with '#;< DEV_AHOY' / '#;< DEV_MAKEFILE').
  if (!in_array('ahoy', $command_wrapper, TRUE)) {
    @unlink('.ahoy.yml');
    remove_tokens_with_content('DEV_AHOY');
  }

  if (!in_array('makefile', $command_wrapper, TRUE)) {
    @unlink('Makefile');
    remove_tokens_with_content('DEV_MAKEFILE');
  }

  // '#;< DEV_COMMAND_WRAPPER' marks documentation for any kept wrapper.
  // '#;< DEV_NO_COMMAND_WRAPPER' marks the direct commands that replace it
  // when no wrapper is kept.
  if ($command_wrapper === []) {
    remove_tokens_with_content('DEV_COMMAND_WRAPPER');
  }
  else {
    remove_tokens_with_content('DEV_NO_COMMAND_WRAPPER');
  }

  process_command_wrapper_text($command_wrapper);

  trim_claude_settings_permissions($command_wrapper);

  remove_tools($tools_remove);

  process_readme($extension_name);

  process_internal($extension_name, $extension_machine_name, $extension_type);

  process_workflow_branches($branches);

  // Remove the sample lifecycle hooks. They demonstrate the naming convention
  // and print a marker line, so they carry no project behaviour. The 'scripts'
  // directory itself stays as the home for project-local hooks.
  if ($remove_examples) {
    @unlink('scripts/assemble-example.sh');
    @unlink('scripts/provision-example.sh');
    @unlink('scripts/start-example.sh');
    @unlink('scripts/stop-example.sh');
  }

  if ($remove_self) {
    // @codeCoverageIgnoreStart
    @unlink(__FILE__);
    // @codeCoverageIgnoreEnd
  }
}

/**
 * Render the text that names the targeted Drupal majors.
 *
 * The template names every supported major in the 'core_version_requirement'
 * of the extension and in the comment that opens 'phpunit.xml'. Each is
 * replaced with the same text for the selected majors.
 *
 * @param array<string> $majors
 *   The selected Drupal majors.
 */
function process_drupal_version_text(array $majors): void {
  $supported = array_map(strval(...), array_keys(drupal_version_options()));

  replace_string_content(core_version_requirement($supported), core_version_requirement($majors));

  // A 'phpunit.d<major>.xml' replaces 'phpunit.xml' in the build of its major.
  $own_config_majors = array_filter($supported, static fn(string $major): bool => file_exists('phpunit.d' . $major . '.xml'));
  replace_string_content(phpunit_config_comment(array_diff($supported, $own_config_majors)), phpunit_config_comment(array_diff($majors, $own_config_majors)));
}

/**
 * Render the text that names the selected command wrappers.
 *
 * The template names both wrappers in phrases such as "`make build` or
 * `ahoy build`". Each is replaced with the same phrase for the selected
 * wrappers. Without a wrapper, these phrases sit in 'DEV_COMMAND_WRAPPER'
 * blocks instead, so only the 'AGENTS.md' note on what runs the tooling
 * installer changes.
 *
 * @param array<string> $command_wrapper
 *   The selected command wrappers.
 */
function process_command_wrapper_text(array $command_wrapper): void {
  if ($command_wrapper === []) {
    replace_string_content('The command wrappers and CI run it', 'CI runs it');

    return;
  }

  $wrappers = array_keys(command_wrapper_binaries());

  foreach (['', 'build', 'lint', 'test'] as $command) {
    replace_string_content(command_wrapper_text($wrappers, $command), command_wrapper_text($command_wrapper, $command));
  }
}

/**
 * Render the 'core_version_requirement' of an extension.
 *
 * @param array<int|string> $majors
 *   The Drupal majors, in any order.
 *
 * @return string
 *   The line, such as 'core_version_requirement: ^10 || ^11'.
 */
function core_version_requirement(array $majors): string {
  $constraints = array_map(static fn(string $major): string => '^' . $major, sort_drupal_majors($majors));

  return 'core_version_requirement: ' . implode(' || ', $constraints);
}

/**
 * Render the comment that opens 'phpunit.xml'.
 *
 * @param array<int|string> $majors
 *   The Drupal majors whose builds use 'phpunit.xml', in any order.
 *
 * @return string
 *   The comment, such as '<!-- Drupal 11 and 12 PHPUnit configuration. -->'.
 *   It names no major when there are none.
 */
function phpunit_config_comment(array $majors): string {
  $majors = sort_drupal_majors($majors);

  if ($majors === []) {
    return '<!-- PHPUnit configuration. -->';
  }

  return '<!-- Drupal ' . join_list($majors) . ' PHPUnit configuration. -->';
}

/**
 * Sort Drupal majors in numeric order and drop duplicates.
 *
 * @param array<int|string> $majors
 *   The Drupal majors.
 *
 * @return list<string>
 *   The sorted majors.
 */
function sort_drupal_majors(array $majors): array {
  $majors = array_unique(array_map(intval(...), $majors));
  sort($majors);

  return array_map(strval(...), $majors);
}

/**
 * Map each command wrapper to the binary that runs it.
 *
 * @return array<string, string>
 *   The binaries, keyed by wrapper, in the order the template names them.
 */
function command_wrapper_binaries(): array {
  return [
    'makefile' => 'make',
    'ahoy' => 'ahoy',
  ];
}

/**
 * Render a command for each of the given command wrappers.
 *
 * @param array<string> $command_wrapper
 *   The command wrappers.
 * @param string $command
 *   The command, or an empty string for the binaries alone.
 *
 * @return string
 *   The commands, such as "`make build` or `ahoy build`".
 */
function command_wrapper_text(array $command_wrapper, string $command = ''): string {
  $commands = [];

  foreach (command_wrapper_binaries() as $wrapper => $binary) {
    if (in_array($wrapper, $command_wrapper, TRUE)) {
      $commands[] = '`' . ($command === '' ? $binary : $binary . ' ' . $command) . '`';
    }
  }

  return join_list($commands, 'or');
}

/**
 * Join items into a list for a sentence, such as 'A, B and C'.
 *
 * @param array<string> $items
 *   The items.
 * @param string $conjunction
 *   The word before the last item.
 *
 * @return string
 *   The list, or an empty string for no items.
 */
function join_list(array $items, string $conjunction = 'and'): string {
  $items = array_values($items);
  $last = array_pop($items);

  if ($last === NULL) {
    return '';
  }

  if ($items === []) {
    return $last;
  }

  return implode(', ', $items) . ' ' . $conjunction . ' ' . $last;
}

/**
 * Trim wrapper-specific permissions from Claude settings to match selection.
 *
 * A failed read (file exists but is unreadable) is treated the same as a
 * missing file: the settings are left untouched rather than aborting the
 * whole 'process()' pipeline over a single non-critical file.
 *
 * @param array<string> $command_wrapper
 *   The selected command wrappers ('ahoy', 'makefile', or both).
 */
function trim_claude_settings_permissions(array $command_wrapper): void {
  $file = '.claude/settings.json';

  if (!file_exists($file)) {
    return;
  }

  $raw = file_get_contents($file);

  if ($raw === FALSE) {
    // @codeCoverageIgnoreStart
    return;
    // @codeCoverageIgnoreEnd
  }

  $config = json_decode($raw, TRUE, 512, JSON_THROW_ON_ERROR);

  if (!is_array($config) || !isset($config['permissions']) || !is_array($config['permissions']) || !isset($config['permissions']['allow']) || !is_array($config['permissions']['allow'])) {
    throw new \RuntimeException('Invalid .claude/settings.json structure.');
  }

  $config['permissions']['allow'] = array_values(array_filter(
    $config['permissions']['allow'],
    static function ($permission) use ($command_wrapper): bool {
      $wrapper = match ($permission) {
        'Bash(ahoy:*)' => 'ahoy',
        'Bash(make:*)' => 'makefile',
        default => NULL,
      };

      return $wrapper === NULL || in_array($wrapper, $command_wrapper, TRUE);
    },
  ));

  $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

  if (file_put_contents($file, $encoded . PHP_EOL) === FALSE) {
    // @codeCoverageIgnoreStart
    throw new \RuntimeException('Unable to write .claude/settings.json.');
    // @codeCoverageIgnoreEnd
  }
}

/**
 * Process the README and CONTRIBUTING files and download placeholder logo.
 *
 * @param string $extension_name
 *   The human-readable extension name.
 */
function process_readme(string $extension_name): void {
  @rename('README.dist.md', 'README.md');
  @rename('CONTRIBUTING.dist.md', 'CONTRIBUTING.md');

  $url = 'https://placehold.jp/000000/ffffff/200x200.png?text=' . str_replace(' ', '+', $extension_name) . '&css=%7B%22border-radius%22%3A%22%20100px%22%7D';
  $logo = @file_get_contents($url);

  if ($logo !== FALSE && $logo !== '') {
    file_put_contents('logo.png', $logo);
  }
}

/**
 * Process internal replacements, renames, and cleanups.
 *
 * @param string $extension_name
 *   The human-readable extension name.
 * @param string $extension_machine_name
 *   The machine name of the extension.
 * @param string $extension_type
 *   The extension type (module or theme).
 */
function process_internal(string $extension_name, string $extension_machine_name, string $extension_type): void {
  $extension_machine_name_class = convert_string($extension_machine_name, 'class_name');

  // Protect the attribution link from bulk replacements.
  $attribution_link = '[Eddy](https://github.com/drevops/eddy)';
  $attribution_link_token = '__ATTRIBUTION_LINK__';
  replace_string_content($attribution_link, $attribution_link_token);

  // Protect the update skill URL from bulk replacements.
  $update_skill_url = 'https://raw.githubusercontent.com/drevops/eddy/main/.eddy/skills/update-consumer-eddy/SKILL.md';
  $update_skill_url_token = '__UPDATE_SKILL_URL__';
  replace_string_content($update_skill_url, $update_skill_url_token);

  replace_string_content('YourNamespace', $extension_machine_name);
  replace_string_content('yournamespace', $extension_machine_name);
  replace_string_content('alexskrypnyk', $extension_machine_name);
  replace_string_content('yourproject', $extension_machine_name);
  replace_string_content('Yourproject logo', $extension_name . ' logo');
  replace_string_content('Your Extension', $extension_name);
  replace_string_content('your extension', $extension_name);
  replace_string_content('Your+Extension', $extension_machine_name);
  replace_string_content('your_extension', $extension_machine_name);
  replace_string_content('your-extension', $extension_machine_name);
  replace_string_content('YourExtension', $extension_machine_name_class);
  replace_string_content('Provides your_extension functionality.', 'Provides ' . $extension_machine_name . ' functionality.');
  replace_string_content('drupal-module', 'drupal-' . $extension_type);
  replace_string_content('Drupal module scaffold FE example used for template testing', 'Provides ' . $extension_machine_name . ' functionality.');
  replace_string_content('Drupal extension scaffold', $extension_name);
  replace_string_content('type: module', 'type: ' . $extension_type);
  replace_string_content('is a Drupal module.', 'is a Drupal ' . $extension_type . '.');
  replace_string_content('[EXTENSION_NAME]', $extension_machine_name);

  // Restore the attribution link.
  replace_string_content($attribution_link_token, $attribution_link);

  // Restore the update skill URL.
  replace_string_content($update_skill_url_token, $update_skill_url);

  remove_string_content('# Uncomment the lines below in your project.');
  uncomment_line('.gitattributes', 'AGENTS.md');
  uncomment_line('.gitattributes', 'CLAUDE.md');
  uncomment_line('.gitattributes', '.claude');
  uncomment_line('.gitattributes', '.ahoy.yml');
  uncomment_line('.gitattributes', '.cspell.json');
  uncomment_line('.gitattributes', '.editorconfig');
  uncomment_line('.gitattributes', '.eslintignore');
  uncomment_line('.gitattributes', '.eslintrc.json');
  uncomment_line('.gitattributes', '.gitattributes');
  uncomment_line('.gitattributes', '.github');
  uncomment_line('.gitattributes', '.gitignore');
  uncomment_line('.gitattributes', '.prettierignore');
  uncomment_line('.gitattributes', '.prettierrc.json');
  uncomment_line('.gitattributes', '.skip_npm_build');
  uncomment_line('.gitattributes', '.stylelintrc.js');
  uncomment_line('.gitattributes', '.twig-cs-fixer.php');
  uncomment_line('.gitattributes', 'Makefile');
  uncomment_line('.gitattributes', 'composer.dev.json');
  uncomment_line('.gitattributes', 'jest.config.js');
  uncomment_line('.gitattributes', 'patches');
  uncomment_line('.gitattributes', 'package-lock.json');
  uncomment_line('.gitattributes', 'package.json');
  uncomment_line('.gitattributes', 'phpcs.xml');
  uncomment_line('.gitattributes', 'phpstan.neon');
  uncomment_line('.gitattributes', 'phpunit.d10.xml');
  uncomment_line('.gitattributes', 'phpunit.xml');
  uncomment_line('.gitattributes', 'rector.php');
  uncomment_line('.gitattributes', 'renovate.json');
  uncomment_line('.gitattributes', 'scripts');
  uncomment_line('.gitattributes', 'tests');
  remove_string_content('# Remove the lines below in your project.');
  remove_string_content('.github/FUNDING.yml export-ignore');
  remove_string_content('LICENSE.txt         export-ignore');
  remove_string_content('install.php         export-subst');

  // Rename extension files.
  @rename('your_extension.info.yml', $extension_machine_name . '.info.yml');
  @rename('your_extension.install', $extension_machine_name . '.install');
  @rename('your_extension.links.menu.yml', $extension_machine_name . '.links.menu.yml');
  @rename('your_extension.module', $extension_machine_name . '.module');
  @rename('your_extension.routing.yml', $extension_machine_name . '.routing.yml');
  @rename('your_extension.services.yml', $extension_machine_name . '.services.yml');
  @rename('config/schema/your_extension.schema.yml', 'config/schema/' . $extension_machine_name . '.schema.yml');
  @rename('src/Form/YourExtensionForm.php', 'src/Form/' . $extension_machine_name_class . 'Form.php');
  @rename('src/YourExtensionService.php', 'src/' . $extension_machine_name_class . 'Service.php');
  @rename('tests/src/Unit/YourExtensionServiceUnitTest.php', 'tests/src/Unit/' . $extension_machine_name_class . 'ServiceUnitTest.php');
  @rename('tests/src/Kernel/YourExtensionServiceKernelTest.php', 'tests/src/Kernel/' . $extension_machine_name_class . 'ServiceKernelTest.php');
  @rename('tests/src/Functional/YourExtensionFunctionalTest.php', 'tests/src/Functional/' . $extension_machine_name_class . 'FunctionalTest.php');
  @rename('tests/src/FunctionalJavascript/YourExtensionFunctionalJavascriptTestBase.php', 'tests/src/FunctionalJavascript/' . $extension_machine_name_class . 'FunctionalJavascriptTestBase.php');
  @rename('tests/src/FunctionalJavascript/YourExtensionSmokeFunctionalJavascriptTest.php', 'tests/src/FunctionalJavascript/' . $extension_machine_name_class . 'SmokeFunctionalJavascriptTest.php');
  @rename('css/your_extension.css', 'css/' . $extension_machine_name . '.css');
  @rename('js/your_extension.js', 'js/' . $extension_machine_name . '.js');
  @rename('js/your_extension.test.js', 'js/' . $extension_machine_name . '.test.js');
  @rename('your_extension.libraries.yml', $extension_machine_name . '.libraries.yml');

  // Remove scaffold files.
  @unlink('LICENSE.txt');
  @unlink('MIGRATION.md');
  @unlink('SECURITY.md');
  @unlink('logo.svg');

  foreach (glob('.github/workflows/eddy-*.yml') ?: [] as $file) {
    @unlink($file);
  }

  remove_dir('.eddy');

  // A project keeps its own skills in '.claude/skills', so Eddy's skills are
  // removed by name.
  foreach (eddy_skills() as $skill) {
    remove_dir('.claude/skills/' . $skill);
  }

  if (is_dir('.claude/skills') && scandir('.claude/skills') === ['.', '..']) {
    rmdir('.claude/skills');
  }

  remove_tokens_with_content('META');
  remove_special_comments();

  normalize_cspell_words();

  if ($extension_type === 'theme') {
    @unlink($extension_machine_name . '.install');
    @unlink($extension_machine_name . '.module');
    @unlink($extension_machine_name . '.routing.yml');
    @unlink($extension_machine_name . '.services.yml');
    @unlink($extension_machine_name . '.links.menu.yml');
    @unlink('src/' . $extension_machine_name_class . 'Service.php');
    file_put_contents($extension_machine_name . '.info.yml', 'base theme: false' . PHP_EOL, FILE_APPEND);
  }
}

/**
 * List the Claude Code skills that maintain Eddy itself.
 *
 * '.eddy/CLAUDE.md' lists the steps for adding a skill.
 *
 * @return list<string>
 *   The names of the skill directories in '.claude/skills'.
 */
function eddy_skills(): array {
  return ['create-eddy-tooling-release-notes'];
}

/**
 * Set the branches that the shipped workflows run on.
 *
 * The template's workflows filter on 'main', the template's own branch. They
 * take the given branches instead. Without any, they take the 'push' branches
 * of the committed 'test.yml', or '1.x' when 'HEAD' holds no project copy of
 * it.
 *
 * @param array<int, string> $branches
 *   The branches, or an empty array to read them from the committed workflow.
 */
function process_workflow_branches(array $branches): void {
  $branches = $branches ?: committed_workflow_branches('.github/workflows/test.yml') ?: ['1.x'];

  set_workflow_branches('.github/workflows/test.yml', $branches);
  set_workflow_branches('.github/workflows/draft-release-notes.yml', $branches);
}

/**
 * Parse a comma-separated list of workflow branches.
 *
 * Each branch is kept as written, quotes included.
 *
 * @param string $list
 *   The list, such as '1.x,2.x'.
 *
 * @return array<int, string>
 *   The branches, or an empty array when the list holds none.
 *
 * @throws \Exception
 *   When a branch holds whitespace or a control character.
 */
function parse_branch_list(string $list): array {
  $branches = array_values(array_filter(array_map(trim(...), explode(',', $list)), static fn(string $branch): bool => $branch !== ''));

  foreach ($branches as $branch) {
    // Each branch is written as 1 line of a YAML sequence.
    if (preg_match('/[\s[:cntrl:]]/', $branch) === 1) {
      throw new \Exception(sprintf('Invalid branch: "%s". Separate branches with commas.', $branch));
    }
  }

  return $branches;
}

/**
 * Read the 'push' branches of the committed copy of a workflow.
 *
 * A copy that still holds '#;' markers is the template's own, not a project's.
 *
 * @param string $file
 *   The workflow path, relative to the current directory.
 *
 * @return array<int, string>
 *   The branches, or an empty array when the current directory has no commit
 *   that holds a project copy of the workflow.
 */
function committed_workflow_branches(string $file): array {
  $output = [];
  $exit_code = 0;
  // './' resolves the path against the current directory rather than the
  // repository root.
  exec(sprintf('git show %s 2>/dev/null', escapeshellarg('HEAD:./' . $file)), $output, $exit_code);

  if ($exit_code !== 0) {
    return [];
  }

  $content = implode("\n", $output);

  if (str_contains($content, '#;')) {
    return [];
  }

  return workflow_push_branches($content);
}

/**
 * Read the branches that the 'push' trigger of a workflow filters on.
 *
 * Reads a block or a single-line flow sequence under 'on.push.branches' and
 * keeps each branch as written, quotes included.
 *
 * @param string $content
 *   The workflow YAML.
 *
 * @return array<int, string>
 *   The branches, or an empty array when the 'push' trigger has no
 *   'branches' filter.
 */
function workflow_push_branches(string $content): array {
  $branches = [];
  $keys = [];
  $list_indent = NULL;

  foreach (explode("\n", $content) as $line) {
    // YAML opens a comment only at a '#' that starts the line or follows
    // whitespace.
    $line = rtrim(preg_replace('/(^|\s)#.*$/', '', $line) ?? $line);
    $text = ltrim($line);

    if ($text === '') {
      continue;
    }

    $indent = strlen($line) - strlen($text);

    // YAML allows the items of a sequence at the indentation of its key.
    if ($list_indent !== NULL && $indent >= $list_indent && str_starts_with($text, '-')) {
      $branches[] = trim(substr($text, 1));

      continue;
    }

    $list_indent = NULL;

    // $keys maps the indentation of each enclosing key to its name.
    $keys = array_filter($keys, static fn(int $key_indent): bool => $key_indent < $indent, ARRAY_FILTER_USE_KEY);

    if (preg_match('/^([\'"]?)([\w-]+)\1:\s*(.*)$/', $text, $matches) !== 1) {
      continue;
    }

    [, , $key, $value] = $matches;

    if ($key === 'branches' && array_values($keys) === ['on', 'push']) {
      if ($value === '') {
        $list_indent = $indent;
      }
      elseif (preg_match('/^\[(.*)\]$/', $value, $flow) === 1) {
        $branches = array_values(array_filter(array_map(trim(...), explode(',', $flow[1])), static fn(string $branch): bool => $branch !== ''));
      }
    }

    $keys[$indent] = $key;
  }

  return $branches;
}

/**
 * Replace the 'main' branch filters of a workflow.
 *
 * @param string $file
 *   The workflow path.
 * @param non-empty-array<int, string> $branches
 *   The branches to filter on instead.
 */
function set_workflow_branches(string $file, array $branches): void {
  if (!file_exists($file)) {
    return;
  }

  $content = file_get_contents($file);

  if ($content === FALSE) {
    // @codeCoverageIgnoreStart
    return;
    // @codeCoverageIgnoreEnd
  }

  // Each replacement line keeps the indentation and the line ending of the
  // line it replaces.
  $content = preg_replace_callback('/^( +)- main(\r?)$/m', static function (array $matches) use ($branches): string {
    $lines = array_map(static fn(string $branch): string => $matches[1] . '- ' . $branch . $matches[2], $branches);

    return implode("\n", $lines);
  }, $content) ?? $content;

  file_put_contents($file, $content);
}

/**
 * Remove deselected development tools from the project.
 *
 * Removing a tool strips its '#;< DEV_<TOOL> ... #;> DEV_<TOOL>' blocks from
 * every project file. Markers for kept tools are stripped later by
 * 'remove_special_comments()'.
 *
 * It also removes the tool's files, directories and 'composer.dev.json'
 * entries listed in 'tool_specs()', and its 'package.json' entries listed in
 * 'npm_specs()'.
 *
 * '.eddy/CLAUDE.md' lists the steps for adding a tool.
 *
 * @param array<string> $tools_remove
 *   The machine names of the tools to remove.
 */
function remove_tools(array $tools_remove): void {
  // FunctionalJavascript tests require PHPUnit; removing PHPUnit removes them.
  if (in_array('phpunit', $tools_remove, TRUE) && !in_array('functional_javascript', $tools_remove, TRUE)) {
    $tools_remove[] = 'functional_javascript';
  }

  $specs = tool_specs();

  foreach ($tools_remove as $tool) {
    if (!isset($specs[$tool])) {
      continue;
    }

    $spec = $specs[$tool];

    remove_tokens_with_content($spec['token']);

    foreach ($spec['files'] as $file) {
      @unlink($file);
    }

    foreach ($spec['dirs'] as $dir) {
      remove_dir($dir);
    }

    remove_composer_dev_dependencies($spec['composer_dev'], $spec['composer_allow_plugins'], $spec['composer_extra']);

    foreach ($spec['composer_scaffold_mappings'] ?? [] as $mapping) {
      remove_composer_scaffold_mapping($mapping);
    }
  }

  // The shared 'npm run lint' pipeline step covers ESLint and Stylelint;
  // remove it only when both are gone.
  if (in_array('eslint', $tools_remove, TRUE) && in_array('stylelint', $tools_remove, TRUE)) {
    remove_tokens_with_content('DEV_NODEJS_LINT');
  }

  remove_npm($tools_remove);
}

/**
 * Define the removal footprint for each selectable development tool.
 *
 * @return array<string, array{
 *   token: string,
 *   files: list<string>,
 *   dirs: list<string>,
 *   composer_dev: list<string>,
 *   composer_allow_plugins: list<string>,
 *   composer_extra: list<string>,
 *   composer_scaffold_mappings?: list<string>,
 *   }>
 *   Map of tool machine name to its removal specification.
 */
function tool_specs(): array {
  return [
    'phpcs' => [
      'token' => 'DEV_PHPCS',
      'files' => ['phpcs.xml'],
      'dirs' => [],
      'composer_dev' => [
        'drupal/coder',
        'drevops/phpcs-standard',
        'dealerdirect/phpcodesniffer-composer-installer',
        'phpcompatibility/php-compatibility',
      ],
      'composer_allow_plugins' => ['dealerdirect/phpcodesniffer-composer-installer'],
      'composer_extra' => ['phpcodesniffer-search-depth'],
    ],
    'phpstan' => [
      'token' => 'DEV_PHPSTAN',
      'files' => ['phpstan.neon'],
      'dirs' => [],
      'composer_dev' => [
        'mglaman/phpstan-drupal',
        'phpstan/phpstan-phpunit',
        'phpstan/extension-installer',
        'jangregor/phpstan-prophecy',
      ],
      'composer_allow_plugins' => ['phpstan/extension-installer'],
      'composer_extra' => [],
    ],
    'rector' => [
      'token' => 'DEV_RECTOR',
      'files' => ['rector.php'],
      'dirs' => [],
      'composer_dev' => ['palantirnet/drupal-rector'],
      'composer_allow_plugins' => [],
      'composer_extra' => [],
    ],
    'twigcs' => [
      'token' => 'DEV_TWIGCS',
      'files' => ['.twig-cs-fixer.php'],
      'dirs' => [],
      'composer_dev' => ['vincentlanglet/twig-cs-fixer'],
      'composer_allow_plugins' => [],
      'composer_extra' => [],
    ],
    'eslint' => [
      'token' => 'DEV_ESLINT',
      'files' => ['.eslintrc.json', '.eslintignore', '.prettierrc.json', '.prettierignore'],
      'dirs' => [],
      'composer_dev' => [],
      'composer_allow_plugins' => [],
      'composer_extra' => [],
      'composer_scaffold_mappings' => ['[web-root]/.eslintrc.json'],
    ],
    'stylelint' => [
      'token' => 'DEV_STYLELINT',
      'files' => ['.stylelintrc.js'],
      'dirs' => [],
      'composer_dev' => [],
      'composer_allow_plugins' => [],
      'composer_extra' => [],
    ],
    'cspell' => [
      'token' => 'DEV_CSPELL',
      'files' => ['.cspell.json'],
      'dirs' => [],
      'composer_dev' => [],
      'composer_allow_plugins' => [],
      'composer_extra' => [],
    ],
    'jest' => [
      'token' => 'DEV_JEST',
      'files' => ['jest.config.js', 'js/your_extension.test.js'],
      'dirs' => [],
      'composer_dev' => [],
      'composer_allow_plugins' => [],
      'composer_extra' => [],
    ],
    'phpunit' => [
      'token' => 'DEV_PHPUNIT',
      'files' => ['phpunit.xml', 'phpunit.d10.xml'],
      'dirs' => ['tests'],
      'composer_dev' => ['phpunit/phpunit', 'phpspec/prophecy-phpunit', 'mikey179/vfsstream'],
      'composer_allow_plugins' => [],
      'composer_extra' => [],
    ],
    'functional_javascript' => [
      'token' => 'DEV_FUNCTIONAL_JAVASCRIPT',
      'files' => [],
      'dirs' => ['tests/src/FunctionalJavascript'],
      'composer_dev' => [
        'behat/mink',
        'behat/mink-browserkit-driver',
        'lullabot/mink-selenium2-driver',
        'symfony/browser-kit',
        'symfony/css-selector',
        'symfony/dom-crawler',
      ],
      'composer_allow_plugins' => [],
      'composer_extra' => [],
    ],
    'renovate' => [
      'token' => 'DEV_RENOVATE',
      'files' => ['renovate.json'],
      'dirs' => [],
      'composer_dev' => [],
      'composer_allow_plugins' => [],
      'composer_extra' => [],
    ],
  ];
}

/**
 * Read 'composer.dev.json'.
 *
 * @return array<int|string, mixed>|null
 *   The decoded configuration, or NULL when the file is missing or holds no
 *   JSON object.
 */
function read_composer_dev_json(): ?array {
  $file = 'composer.dev.json';

  if (!file_exists($file)) {
    return NULL;
  }

  $raw = file_get_contents($file);

  if ($raw === FALSE) {
    // @codeCoverageIgnoreStart
    return NULL;
    // @codeCoverageIgnoreEnd
  }

  $config = json_decode($raw, TRUE, 512, JSON_THROW_ON_ERROR);

  return is_array($config) ? $config : NULL;
}

/**
 * Write 'composer.dev.json', keeping its empty 'patches' map a JSON object.
 *
 * 'json_decode()' turns the template's empty '"patches": {}' into an array,
 * which would re-encode as '[]'; restore it to an object so the manifest keeps
 * its original shape.
 *
 * @param array<int|string, mixed> $config
 *   The decoded and modified configuration.
 */
function write_composer_dev_json(array $config): void {
  if (isset($config['extra']) && is_array($config['extra']) && ($config['extra']['patches'] ?? NULL) === []) {
    $config['extra']['patches'] = new \stdClass();
  }

  $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

  if (file_put_contents('composer.dev.json', $encoded . PHP_EOL) === FALSE) {
    // @codeCoverageIgnoreStart
    throw new \RuntimeException('Unable to write composer.dev.json.');
    // @codeCoverageIgnoreEnd
  }
}

/**
 * Remove development dependencies and config keys from 'composer.dev.json'.
 *
 * @param array<string> $packages
 *   The 'require-dev' package names to remove.
 * @param array<string> $allow_plugins
 *   The 'config.allow-plugins' keys to remove.
 * @param array<string> $extra_keys
 *   The 'extra' keys to remove.
 */
function remove_composer_dev_dependencies(array $packages, array $allow_plugins = [], array $extra_keys = []): void {
  if ($packages === [] && $allow_plugins === [] && $extra_keys === []) {
    return;
  }

  $config = read_composer_dev_json();

  if ($config === NULL) {
    return;
  }

  if (isset($config['require-dev']) && is_array($config['require-dev'])) {
    foreach ($packages as $package) {
      unset($config['require-dev'][$package]);
    }
  }

  if (isset($config['extra']) && is_array($config['extra'])) {
    foreach ($extra_keys as $key) {
      unset($config['extra'][$key]);
    }
  }

  if (isset($config['config']) && is_array($config['config'])) {
    if (isset($config['config']['allow-plugins']) && is_array($config['config']['allow-plugins'])) {
      foreach ($allow_plugins as $plugin) {
        unset($config['config']['allow-plugins'][$plugin]);
      }

      if ($config['config']['allow-plugins'] === []) {
        unset($config['config']['allow-plugins']);
      }
    }

    if ($config['config'] === []) {
      unset($config['config']);
    }
  }

  write_composer_dev_json($config);
}

/**
 * Remove a 'drupal-scaffold' file-mapping entry from 'composer.dev.json'.
 *
 * The 'extra.drupal-scaffold.file-mapping' block is nested, so the generic
 * 'composer_extra' top-level key removal cannot reach it. Empty 'file-mapping'
 * and 'drupal-scaffold' parents are pruned once their last child is gone.
 *
 * @param string $mapping
 *   The file-mapping key to remove (e.g. '[web-root]/.eslintrc.json').
 */
function remove_composer_scaffold_mapping(string $mapping): void {
  $config = read_composer_dev_json();

  if ($config === NULL) {
    return;
  }

  if (!isset($config['extra']) || !is_array($config['extra'])) {
    return;
  }

  if (!isset($config['extra']['drupal-scaffold']) || !is_array($config['extra']['drupal-scaffold'])) {
    return;
  }

  if (!isset($config['extra']['drupal-scaffold']['file-mapping']) || !is_array($config['extra']['drupal-scaffold']['file-mapping'])) {
    return;
  }

  unset($config['extra']['drupal-scaffold']['file-mapping'][$mapping]);

  if ($config['extra']['drupal-scaffold']['file-mapping'] === []) {
    unset($config['extra']['drupal-scaffold']['file-mapping']);
  }

  if ($config['extra']['drupal-scaffold'] === []) {
    unset($config['extra']['drupal-scaffold']);
  }

  write_composer_dev_json($config);
}

/**
 * Set a value under 'extra.eddy' in 'composer.dev.json'.
 *
 * @param string $key
 *   The key, such as 'drupal-version'.
 * @param string $value
 *   The value.
 */
function set_composer_dev_eddy_value(string $key, string $value): void {
  $config = read_composer_dev_json();

  if ($config === NULL) {
    return;
  }

  $extra = is_array($config['extra'] ?? NULL) ? $config['extra'] : [];
  $eddy = is_array($extra['eddy'] ?? NULL) ? $extra['eddy'] : [];
  $eddy[$key] = $value;
  $extra['eddy'] = $eddy;
  $config['extra'] = $extra;

  write_composer_dev_json($config);
}

/**
 * Read the template version from 'git describe' output.
 *
 * @param string $describe
 *   The output, such as '5.0.0' or '5.0.0-3-g1a2b3c4'.
 *
 * @return string|null
 *   The version, or NULL when the value is not 'git describe' output, such as
 *   an unexpanded placeholder or an empty string.
 */
function template_version(string $describe): ?string {
  return preg_match('/^[\w.+-]+$/', $describe) === 1 ? $describe : NULL;
}

/**
 * Remove npm devDependencies and scripts for deselected JavaScript tools.
 *
 * The aggregate 'lint' and 'lint-fix' scripts chain per-language sub-scripts;
 * after dropping a tool's sub-scripts they are rebuilt from the survivors (or
 * removed when none remain). 'package.json' itself is kept - it still serves
 * the example module JavaScript.
 *
 * @param array<string> $tools_remove
 *   The machine names of the tools to remove.
 */
function remove_npm(array $tools_remove): void {
  $npm_specs = npm_specs();
  $remove = array_intersect($tools_remove, array_keys($npm_specs));

  if ($remove === []) {
    return;
  }

  $file = 'package.json';

  if (!file_exists($file)) {
    // @codeCoverageIgnoreStart
    return;
    // @codeCoverageIgnoreEnd
  }

  $raw = file_get_contents($file);

  if ($raw === FALSE) {
    // @codeCoverageIgnoreStart
    return;
    // @codeCoverageIgnoreEnd
  }

  $config = json_decode($raw, TRUE, 512, JSON_THROW_ON_ERROR);

  if (!is_array($config)) {
    // @codeCoverageIgnoreStart
    return;
    // @codeCoverageIgnoreEnd
  }

  $dev_dependencies = is_array($config['devDependencies'] ?? NULL) ? $config['devDependencies'] : [];
  $scripts = is_array($config['scripts'] ?? NULL) ? $config['scripts'] : [];

  foreach ($remove as $tool) {
    foreach ($npm_specs[$tool]['dev'] as $dep) {
      unset($dev_dependencies[$dep]);
    }

    foreach ($npm_specs[$tool]['scripts'] as $script) {
      unset($scripts[$script]);
    }
  }

  $scripts = rebuild_npm_chain($scripts, 'lint', ['lint-js', 'lint-css']);
  $scripts = rebuild_npm_chain($scripts, 'lint-fix', ['lint-fix-js', 'lint-fix-css']);
  $config['scripts'] = $scripts;

  if ($dev_dependencies === []) {
    unset($config['devDependencies']);
  }
  else {
    $config['devDependencies'] = $dev_dependencies;
  }

  $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
  // JSON_PRETTY_PRINT indents with four spaces; package.json uses two.
  $encoded = preg_replace_callback('/^ +/m', static fn(array $m): string => str_repeat(' ', intdiv(strlen($m[0]), 2)), $encoded) ?? $encoded;

  if (file_put_contents($file, $encoded . PHP_EOL) === FALSE) {
    // @codeCoverageIgnoreStart
    throw new \RuntimeException('Unable to write ' . $file . '.');
    // @codeCoverageIgnoreEnd
  }
}

/**
 * Define the npm removal footprint for each JavaScript tool.
 *
 * @return array<string, array{dev: list<string>, scripts: list<string>}>
 *   Map of tool machine name to its 'devDependencies' and 'scripts' keys.
 */
function npm_specs(): array {
  return [
    'eslint' => [
      'dev' => [
        'eslint',
        'eslint-config-airbnb-base',
        'eslint-config-prettier',
        'eslint-plugin-import',
        'eslint-plugin-jsdoc',
        'eslint-plugin-no-jquery',
        'eslint-plugin-prettier',
        'eslint-plugin-yml',
        'prettier',
        '@homer0/prettier-plugin-jsdoc',
      ],
      'scripts' => ['lint-js', 'lint-fix-js'],
    ],
    'stylelint' => [
      'dev' => ['stylelint', 'stylelint-config-standard', 'stylelint-order'],
      'scripts' => ['lint-css', 'lint-fix-css'],
    ],
    'cspell' => [
      'dev' => ['cspell'],
      'scripts' => ['lint-spell'],
    ],
    'jest' => [
      'dev' => ['jest', 'jest-environment-jsdom'],
      'scripts' => ['test'],
    ],
  ];
}

/**
 * Rebuild an aggregate npm script from its surviving sub-scripts.
 *
 * @param array<string, mixed> $scripts
 *   The 'scripts' map.
 * @param string $name
 *   The aggregate script name (e.g. 'lint').
 * @param array<string> $parts
 *   The sub-script names the aggregate chains (e.g. 'lint-js', 'lint-css').
 *
 * @return array<string, mixed>
 *   The updated 'scripts' map.
 */
function rebuild_npm_chain(array $scripts, string $name, array $parts): array {
  $surviving = array_values(array_filter($parts, static fn(string $part): bool => isset($scripts[$part])));

  if ($surviving === []) {
    unset($scripts[$name]);

    return $scripts;
  }

  $scripts[$name] = implode(' && ', array_map(static fn(string $part): string => 'npm run ' . $part, $surviving));

  return $scripts;
}

/**
 * Convert a string to a specific format.
 *
 * @param string $input
 *   The input string to convert.
 * @param string $type
 *   The conversion type.
 *
 * @return string
 *   The converted string.
 */
function convert_string(string $input, string $type): string {
  return match ($type) {
    'file_name', 'route_path', 'deployment_id', 'function_name', 'ui_id', 'cli_command' => strtolower(str_replace(' ', '_', $input)),
    'domain_name', 'package_namespace' => str_replace('-', '', strtolower(str_replace(' ', '_', $input))),
    'namespace', 'class_name' => implode('', array_map(ucfirst(...), array_map(strtolower(...), preg_split('/[-_ ]+/', $input, -1, PREG_SPLIT_NO_EMPTY) ?: []))),
    'package_name' => strtolower(str_replace(' ', '-', $input)),
    'log_entry', 'code_comment_title' => $input,
    default => throw new \InvalidArgumentException('Invalid conversion type: ' . $type),
  };
}

/**
 * Replace string content in all project files recursively.
 *
 * @param string $needle
 *   The string to search for.
 * @param string $replacement
 *   The replacement string.
 */
function replace_string_content(string $needle, string $replacement): void {
  foreach (get_files() as $file) {
    $content = file_get_contents($file);

    if ($content === FALSE) {
      // @codeCoverageIgnoreStart
      continue;
      // @codeCoverageIgnoreEnd
    }

    if (!str_contains($content, $needle)) {
      continue;
    }

    file_put_contents($file, str_replace($needle, $replacement, $content));
  }
}

/**
 * Remove lines starting with the given token from all project files.
 *
 * @param string $token
 *   The token to match at the start of lines.
 */
function remove_string_content(string $token): void {
  foreach (get_files() as $file) {
    $content = file_get_contents($file);

    if ($content === FALSE) {
      // @codeCoverageIgnoreStart
      continue;
      // @codeCoverageIgnoreEnd
    }

    if (!str_contains($content, $token)) {
      continue;
    }

    $lines = explode("\n", $content);
    $lines = array_filter($lines, static fn(string $line): bool => !str_starts_with($line, $token));
    file_put_contents($file, implode("\n", $lines));
  }
}

/**
 * Remove blocks between token markers from all project files.
 *
 * Removes all content between lines containing "#;< TOKEN" and "#;> TOKEN"
 * markers, inclusive.
 *
 * @param string $token
 *   The token name used in the markers.
 */
function remove_tokens_with_content(string $token): void {
  $start_marker = '#;< ' . $token;
  $end_marker = '#;> ' . $token;

  foreach (get_files() as $file) {
    $content = file_get_contents($file);

    if ($content === FALSE) {
      // @codeCoverageIgnoreStart
      continue;
      // @codeCoverageIgnoreEnd
    }

    if (!str_contains($content, $end_marker)) {
      continue;
    }

    $lines = explode("\n", $content);
    $result = [];
    $inside = FALSE;

    foreach ($lines as $line) {
      if (str_contains($line, $start_marker)) {
        $inside = TRUE;
        continue;
      }

      if (str_contains($line, $end_marker)) {
        $inside = FALSE;
        continue;
      }

      if (!$inside) {
        $result[] = $line;
      }
    }

    file_put_contents($file, implode("\n", $result));
  }
}

/**
 * Uncomment every line in a file that starts with "# " and the given string.
 *
 * @param string $file
 *   The file to modify.
 * @param string $start_string
 *   The string that follows "# " at the start of the line.
 */
function uncomment_line(string $file, string $start_string): void {
  if (!file_exists($file)) {
    return;
  }

  $content = file_get_contents($file);

  if ($content === FALSE) {
    // @codeCoverageIgnoreStart
    return;
    // @codeCoverageIgnoreEnd
  }

  $prefix = '# ' . $start_string;
  $lines = explode("\n", $content);

  foreach ($lines as &$line) {
    if (str_starts_with($line, $prefix)) {
      $line = substr($line, 2);
    }
  }

  unset($line);
  file_put_contents($file, implode("\n", $lines));
}

/**
 * Remove all lines containing special comment markers from project files.
 */
function remove_special_comments(): void {
  foreach (get_files() as $file) {
    $content = file_get_contents($file);

    if ($content === FALSE) {
      // @codeCoverageIgnoreStart
      continue;
      // @codeCoverageIgnoreEnd
    }

    if (!str_contains($content, '#;')) {
      continue;
    }

    $lines = explode("\n", $content);
    $lines = array_filter($lines, static fn(string $line): bool => !str_contains($line, '#;'));
    file_put_contents($file, implode("\n", $lines));
  }
}

/**
 * Deduplicate the CSpell `words` allowlist after bulk replacements.
 *
 * Scaffold placeholders (alexskrypnyk, yournamespace, yourproject) are also
 * present in the CSpell `words` array; the global rename inside
 * `process_internal()` rewrites them all to the extension machine name,
 * producing duplicate entries. Read, deduplicate, sort, and write back.
 */
function normalize_cspell_words(): void {
  if (!file_exists('.cspell.json')) {
    return;
  }

  $raw = file_get_contents('.cspell.json');

  if ($raw === FALSE) {
    // @codeCoverageIgnoreStart
    return;
    // @codeCoverageIgnoreEnd
  }

  $config = json_decode($raw, TRUE, 512, JSON_THROW_ON_ERROR);

  if (!is_array($config)) {
    return;
  }

  $raw_words = $config['words'] ?? NULL;

  if (!is_array($raw_words)) {
    return;
  }

  $deduped = [];

  foreach ($raw_words as $w) {
    if (!is_string($w)) {
      continue;
    }

    $deduped[strtolower($w)] ??= $w;
  }

  $words = array_values($deduped);
  sort($words, SORT_FLAG_CASE | SORT_STRING);
  $config['words'] = $words;

  $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

  if (file_put_contents('.cspell.json', $encoded . PHP_EOL) === FALSE) {
    // @codeCoverageIgnoreStart
    throw new \RuntimeException('Unable to write .cspell.json.');
    // @codeCoverageIgnoreEnd
  }
}

/**
 * Get all non-binary template files in the project.
 *
 * A path that 'is_template_path()' rejects is skipped, and so is everything
 * inside a rejected directory.
 *
 * @return array<string>
 *   Array of file paths.
 */
function get_files(): array {
  $root = (string) getcwd();
  $gitignore = $root . '/.gitignore';
  $rules = is_file($gitignore) ? gitignore_rules((string) file_get_contents($gitignore)) : [];

  $directory = new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS);
  $filter = new \RecursiveCallbackFilterIterator($directory, static function (\SplFileInfo $current) use ($root, $rules): bool {
    $path = str_replace(DIRECTORY_SEPARATOR, '/', substr($current->getPathname(), strlen($root) + 1));

    return is_template_path($path, $current->isDir(), $rules);
  });
  $iterator = new \RecursiveIteratorIterator($filter);

  $files = [];

  /** @var \SplFileInfo $item */
  foreach ($iterator as $item) {
    if ($item->isFile() && !is_binary_file($item->getPathname())) {
      $files[] = $item->getPathname();
    }
  }

  return $files;
}

/**
 * Check whether a project path belongs to the template.
 *
 * The '.git', '.idea', 'vendor' and 'node_modules' directories do not, at any
 * depth. Neither does anything in '.claude' except 'settings.json', nor any
 * path that the '.gitignore' rules ignore.
 *
 * @param string $path
 *   The path, relative to the project root and separated with '/'.
 * @param bool $is_dir
 *   Whether the path is a directory.
 * @param list<array{regex: string, negate: bool, dir_only: bool}> $rules
 *   The rules of the project root '.gitignore'.
 *
 * @return bool
 *   TRUE when the path belongs to the template, FALSE otherwise.
 */
function is_template_path(string $path, bool $is_dir, array $rules): bool {
  if ($is_dir && in_array(basename($path), ['.git', '.idea', 'vendor', 'node_modules'], TRUE)) {
    return FALSE;
  }

  // A project keeps local settings and skills in '.claude' too.
  if (str_starts_with($path, '.claude/') && $path !== '.claude/settings.json') {
    return FALSE;
  }

  return !is_gitignored($path, $is_dir, $rules);
}

/**
 * Parse '.gitignore' content into rules.
 *
 * Handles comments, blank lines, backslash escapes, trailing spaces, '!'
 * negation, anchoring and directory-only patterns. 'gitignore_glob_regex()'
 * converts the wildcards.
 *
 * @param string $content
 *   The '.gitignore' content.
 *
 * @return list<array{regex: string, negate: bool, dir_only: bool}>
 *   The rules in file order, each with a regular expression that matches a
 *   whole path relative to the '.gitignore' directory.
 */
function gitignore_rules(string $content): array {
  $rules = [];

  foreach (preg_split('/\r?\n/', $content) ?: [] as $line) {
    // A trailing space belongs to the pattern only when it is escaped.
    $line = preg_replace('/(?<!\\\\) +$/', '', $line) ?? $line;

    if ($line === '' || str_starts_with($line, '#')) {
      continue;
    }

    $negate = str_starts_with($line, '!');
    $pattern = $negate ? substr($line, 1) : $line;
    $dir_only = str_ends_with($pattern, '/');
    $pattern = rtrim($pattern, '/');

    if ($pattern === '') {
      continue;
    }

    // A pattern without a '/' matches a name at any depth.
    $depth = str_contains($pattern, '/') ? '' : '(?:.*/)?';
    $regex = '#\A' . $depth . gitignore_glob_regex(ltrim($pattern, '/')) . '\z#s';
    $rules[] = ['regex' => $regex, 'negate' => $negate, 'dir_only' => $dir_only];
  }

  return $rules;
}

/**
 * Convert a '.gitignore' glob into the body of a regular expression.
 *
 * '*', '?' and a bracket expression, such as '[a-z]' or '[!0-9]', match
 * within 1 path segment. A '**' segment matches 0 or more directories, or
 * everything inside a directory when it ends the glob. A backslash makes the
 * next character literal.
 *
 * @param string $glob
 *   The glob, without a leading or trailing '/'.
 *
 * @return string
 *   The body of a regular expression delimited with '#'.
 */
function gitignore_glob_regex(string $glob): string {
  $regex = '';
  $length = strlen($glob);

  for ($i = 0; $i < $length; $i++) {
    $char = $glob[$i];
    $segment_start = $i === 0 || $glob[$i - 1] === '/';

    if ($char === '\\' && $i + 1 < $length) {
      $regex .= preg_quote($glob[++$i], '#');
    }
    elseif ($segment_start && substr($glob, $i, 3) === '**/') {
      $regex .= '(?:.*/)?';
      $i += 2;
    }
    elseif ($segment_start && substr($glob, $i) === '**') {
      $regex .= '.+';
      $i++;
    }
    elseif ($char === '*') {
      $regex .= '[^/]*';
    }
    elseif ($char === '?') {
      $regex .= '[^/]';
    }
    elseif ($char === '[' && preg_match('/\G\[([!^]?)(\]?(?:\[:[a-z]+:\]|\\\\.|[^\]\\\\])*)\]/', $glob, $match, 0, $i) === 1) {
      $regex .= '[' . ($match[1] === '' ? '' : '^/') . gitignore_class_members($match[2]) . ']';
      $i += strlen($match[0]) - 1;
    }
    else {
      $regex .= preg_quote($char, '#');
    }
  }

  return $regex;
}

/**
 * Convert the members of a '.gitignore' bracket expression.
 *
 * A POSIX class, such as '[:digit:]', and a '-' range stay as written. Any
 * other character is literal, with or without a backslash.
 *
 * @param string $members
 *   The members, without the brackets and the '!' or '^' negation.
 *
 * @return string
 *   The members of a regular expression character class.
 */
function gitignore_class_members(string $members): string {
  $class = '';
  $length = strlen($members);

  for ($i = 0; $i < $length; $i++) {
    $char = $members[$i];

    if ($char === '[' && preg_match('/\G\[:[a-z]+:\]/', $members, $match, 0, $i) === 1) {
      $class .= $match[0];
      $i += strlen($match[0]) - 1;
    }
    elseif ($char === '\\' && $i + 1 < $length) {
      $class .= preg_quote($members[++$i], '#');
    }
    else {
      $class .= $char === '-' ? '-' : preg_quote($char, '#');
    }
  }

  return $class;
}

/**
 * Check whether '.gitignore' rules ignore a path.
 *
 * The last rule that matches decides, so a '!' rule re-includes what an
 * earlier rule ignores. The path is checked alone: a walk skips the contents
 * of an ignored directory, the way git does.
 *
 * @param string $path
 *   The path, relative to the '.gitignore' directory and separated with '/'.
 * @param bool $is_dir
 *   Whether the path is a directory.
 * @param list<array{regex: string, negate: bool, dir_only: bool}> $rules
 *   The rules from 'gitignore_rules()'.
 *
 * @return bool
 *   TRUE when the rules ignore the path, FALSE otherwise.
 */
function is_gitignored(string $path, bool $is_dir, array $rules): bool {
  $ignored = FALSE;

  foreach ($rules as $rule) {
    if ($rule['dir_only'] && !$is_dir) {
      continue;
    }

    if (preg_match($rule['regex'], $path) === 1) {
      $ignored = !$rule['negate'];
    }
  }

  return $ignored;
}

/**
 * Check if a file is binary by looking for null bytes.
 *
 * @param string $file
 *   The file path to check.
 *
 * @return bool
 *   TRUE if the file is binary, FALSE otherwise.
 */
function is_binary_file(string $file): bool {
  $handle = fopen($file, 'rb');

  if ($handle === FALSE) {
    return TRUE;
  }

  $chunk = fread($handle, 8192);
  fclose($handle);

  if ($chunk === FALSE) {
    // @codeCoverageIgnoreStart
    return TRUE;
    // @codeCoverageIgnoreEnd
  }

  return str_contains($chunk, "\0");
}

/**
 * Remove directory recursively with all files.
 *
 * @param string $dir
 *   Path to the directory to remove.
 */
function remove_dir(string $dir): void {
  if (!is_dir($dir)) {
    return;
  }

  $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);

  /** @var \SplFileInfo $item */
  foreach ($items as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
  }

  rmdir($dir);
}

// Entrypoint.
//
// @codeCoverageIgnoreStart
ini_set('display_errors', 1);

if (PHP_SAPI !== 'cli' || !empty($_SERVER['REMOTE_ADDR'])) {
  die('This script can be only ran from the command line.');
}

// Allow to skip the script run.
if (getenv('SCRIPT_RUN_SKIP') != 1) {
  set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
      // This error code is not included in error_reporting - continue
      // execution with the normal error handler.
      return FALSE;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
  });

  try {
    $argv = is_array($_SERVER['argv'] ?? NULL) ? array_filter($_SERVER['argv'], is_string(...)) : [];
    // The function should not provide an exit code but rather throw exceptions.
    main($argv);
  }
  catch (\ErrorException $exception) {
    if ($exception->getSeverity() <= E_USER_WARNING) {
      print PHP_EOL . 'RUNTIME ERROR: ' . $exception->getMessage() . PHP_EOL;
      exit($exception->getCode() === 0 ? 1 : $exception->getCode());
    }
  }
  catch (\Exception $exception) {
    print PHP_EOL . 'ERROR: ' . $exception->getMessage() . PHP_EOL;
    exit($exception->getCode() == 0 ? 1 : $exception->getCode());
  }
}
// @codeCoverageIgnoreEnd
