#!/usr/bin/env php
<?php

/**
 * @file
 * Install the template as a new Drupal extension, based on user input.
 *
 * It runs once per fresh copy of the template and deletes itself unless the
 * 'Remove this script' prompt is answered 'no'. In an empty directory, it
 * downloads the template of its own version first and runs the installer of
 * that template.
 *
 * Environment variables:
 * - SCRIPT_RUN_SKIP: Set to '1' to skip running of the script. Useful when
 *   unit-testing or requiring this file from other files.
 * - INSTALL_*: Set environment variables to pre-fill prompts
 *   (e.g. INSTALL_NAME, INSTALL_TYPE, INSTALL_DRUPAL_VERSION).
 * - INSTALL_BRANCHES: The branches the shipped workflows run on,
 *   comma-separated. Defaults to the 'push' branches of the committed
 *   '.github/workflows/test.yml', or '1.x'.
 * - INSTALL_GITHUB_REPOSITORY: The GitHub repository the README badges name,
 *   as 'owner/name'. Defaults to the GitHub 'origin' remote of the git
 *   repository that the current directory is the root of, or to the machine
 *   name for both parts.
 * - INSTALL_TEMPLATE_ARCHIVE: The '.tar.gz' archive that an installer in an
 *   empty directory downloads, as a URL or a path. Defaults to the archive in
 *   'TEMPLATE_ARCHIVE_URL', or to the GitHub archive of the version in
 *   'TEMPLATE_VERSION'.
 * - INSTALL_TEMPLATE_ARCHIVE_SHA256: The SHA-256 checksum that the archive in
 *   'INSTALL_TEMPLATE_ARCHIVE' must match.
 *
 * Usage:
 * @code
 * php install.php
 * php install.php --help
 * php install.php --version
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

/**
 * The URL of the template archive of a release.
 *
 * The documentation build replaces the placeholder with the archive that the
 * release workflow publishes. A copy that no build stamped keeps it.
 */
const TEMPLATE_ARCHIVE_URL = '__EDDY_TEMPLATE_ARCHIVE_URL__';

/**
 * The SHA-256 checksum of the archive in 'TEMPLATE_ARCHIVE_URL'.
 *
 * The documentation build stamps it with the URL.
 */
const TEMPLATE_ARCHIVE_SHA256 = '__EDDY_TEMPLATE_ARCHIVE_SHA256__';

// phpcs:disable
// @embed-start class=Prompty version=1.2.0 mode=compact sha256=6e8825e512058c4b000ee1387b7b05cc74cfb7f7c84d3eabf25eeb371ba0ed83 embedder=4e26222af3dbfc34aeedf171d0ba3e1ce01a596d6ba314aa61a8ff6320f908ba
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
 */
// @phpstan-ignore-next-line
class Prompty{protected const ESCAPE_TIMEOUT=50000;protected const NULL_DEVICE=PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null';protected const DEFAULT_COLUMNS=80;protected const COLUMNS_REFRESH=1;protected const int SPINNER_INTERVAL=80000;protected const int STREAM_WINDOW=5;protected const array RESERVED_CTRL_KEYS=['ctrl-c','ctrl-h','ctrl-i','ctrl-j','ctrl-m'];protected const array NOTE_TYPES=['info','success','warning','error'];protected const WIDE_PATTERN='/[\x{1100}-\x{115F}\x{231A}\x{231B}\x{2329}\x{232A}\x{23E9}-\x{23EC}\x{23F0}\x{23F3}\x{25FD}\x{25FE}\x{2614}\x{2615}'.'\x{2648}-\x{2653}\x{267F}\x{2693}\x{26A1}\x{26AA}\x{26AB}\x{26BD}\x{26BE}\x{26C4}\x{26C5}\x{26CE}\x{26D4}\x{26EA}\x{26F2}\x{26F3}\x{26F5}'.'\x{26FA}\x{26FD}\x{2705}\x{270A}\x{270B}\x{2728}\x{274C}\x{274E}\x{2753}-\x{2755}\x{2757}\x{2795}-\x{2797}\x{27B0}\x{27BF}\x{2B1B}\x{2B1C}'.'\x{2B50}\x{2B55}\x{2E80}-\x{303E}\x{3041}-\x{33FF}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{A000}-\x{A4CF}\x{A960}-\x{A97F}\x{AC00}-\x{D7A3}'.'\x{F900}-\x{FAFF}\x{FE0F}\x{FE10}-\x{FE19}\x{FE30}-\x{FE6F}\x{FF00}-\x{FF60}\x{FFE0}-\x{FFE6}\x{1F004}\x{1F0CF}\x{1F18E}\x{1F191}-\x{1F19A}'.'\x{1F1E6}-\x{1F1FF}\x{1F200}-\x{1F251}\x{1F300}-\x{1F64F}\x{1F680}-\x{1F6FF}\x{1F7E0}-\x{1F7EB}\x{1F90C}-\x{1F9FF}\x{1FA70}-\x{1FAFF}'.'\x{20000}-\x{3FFFD}]/u';public const VERSION='1.2.0';protected static?self $L=NULL;protected static bool $k=FALSE;protected static?\Closure $P=NULL;protected?string $l=NULL;protected array $c=[];protected array $o=[];protected?array $m=NULL;protected bool $w=FALSE;protected int $D=0;protected array $p=[];protected array $E=[];protected array $J=[];protected?string $x=NULL;protected?int $y=NULL;protected $M;protected array $s=['bar'=>'│','completed'=>'◆','active'=>'◇','intro'=>'┌','outro'=>'└','section'=>'├','pointer'=>'❯','radio_on'=>'●','radio_off'=>'○','check_on'=>'◼','check_off'=>'◻','hint_arrow'=>'↳','error'=>'▲','scroll_up'=>'↑','scroll_down'=>'↓','success'=>'✓','spinner'=>'◒◐◓◑','ellipsis'=>'…','box_top_left'=>'┌','box_top_right'=>'┐','box_bottom_left'=>'└','box_bottom_right'=>'┘','box_horizontal'=>'─','box_vertical'=>'│','box_cross'=>'┼','box_top_tee'=>'┬','box_bottom_tee'=>'┴','box_left_tee'=>'├','box_right_tee'=>'┤','legend_vertical'=>'↑↓','legend_horizontal'=>'←→','legend_separator'=>'·','note_info'=>'●','note_success'=>'✓','note_warning'=>'▲','note_error'=>'✕',];protected array $t=['bar'=>'|','completed'=>'+','active'=>'o','intro'=>'#','outro'=>'#','section'=>'#','pointer'=>'>','radio_on'=>'(*)','radio_off'=>'( )','check_on'=>'[x]','check_off'=>'[ ]','hint_arrow'=>'-->','error'=>'!','scroll_up'=>'^','scroll_down'=>'v','success'=>'+','spinner'=>'|/-\\','ellipsis'=>'...','box_top_left'=>'+','box_top_right'=>'+','box_bottom_left'=>'+','box_bottom_right'=>'+','box_horizontal'=>'-','box_vertical'=>'|','box_cross'=>'+','box_top_tee'=>'+','box_bottom_tee'=>'+','box_left_tee'=>'+','box_right_tee'=>'+','legend_vertical'=>'up/down','legend_horizontal'=>'left/right','legend_separator'=>'-','note_info'=>'*','note_success'=>'+','note_warning'=>'!','note_error'=>'x',];protected array $a;protected array $e=['reset'=>"\033[0m",'dim'=>"\033[2m",'dim_italic'=>"\033[2;3m",'cyan'=>"\033[36m",'green'=>"\033[32m",'red'=>"\033[31m",'gray'=>"\033[90m",'bold'=>"\033[1m",'white'=>"\033[37m",'yellow'=>"\033[33m",'blue'=>"\033[34m",'italic'=>"\033[3m",'underline'=>"\033[4m",'note_info'=>"\033[34m",'note_success'=>"\033[32m",'note_warning'=>"\033[33m",'note_error'=>"\033[31m",'input_default'=>"\033[2m",'input_cursor'=>"\033[36;7m",];protected array $q;protected array $b=['indent'=>'  ','hint_indent'=>'    ','hint_cont'=>'      ',];protected array $u=['yes'=>'Yes','no'=>'No','cancelled'=>'(cancelled)','none'=>'None','separator'=>'/','more'=>'more','required'=>'Required.','done'=>'Done','failed'=>'Failed','line'=>'line','lines'=>'lines','step_total'=>'(%d/%d)','legend_move'=>'move','legend_toggle'=>'toggle','legend_enter'=>'enter confirm','legend_space'=>'space toggle','legend_type'=>'type to filter','legend_yes_no'=>'y/n choose','legend_any_key'=>'any key continue','legend_continue'=>'enter continue','legend_back'=>'back',];protected?bool $v=NULL;protected?bool $n=NULL;protected string $z='PROMPTY_';protected array $A=['1','true','yes'];protected array $B=['0','false','no'];protected $h;protected bool $d=TRUE;protected int $C=0;protected bool $F=TRUE;protected int $j=0;protected bool $G=FALSE;protected string $r='ctrl-u';protected?int $Q=NULL;protected?int $N=NULL;protected float $R=0.0;protected?bool $S=NULL;protected array $f=[];protected?int $K=NULL;protected array $H=[];protected?string $I=NULL;protected function __construct(){$a=$this;if($a->v===NULL){$c=getenv('LANG')?:getenv('LC_ALL')?:getenv('LC_CTYPE')?:setlocale(LC_CTYPE,'0')?:'';$a->v=stripos($c,'utf')!==FALSE;}$a->q=$a->e;if($a->n===NULL){$b=getenv('NO_COLOR');$a->n=($b!==FALSE&&$b!=='')||getenv('TERM')==='dumb'?FALSE:TRUE;}$a->bc();}protected static function j():static{if(!static::$L instanceof Prompty){static::$L=new static();}return static::$L;}protected function bc():void{$a=$this;$a->a=$a->v?$a->s:$a->t;$a->e=$a->n?$a->q:array_fill_keys(array_keys($a->q),'');}protected function dh():array{$a=$this;return['symbols_unicode'=>$a->s,'symbols_ascii'=>$a->t,'colors'=>$a->q,'spacing'=>$a->b,'labels'=>$a->u,'unicode'=>$a->v,'ansi'=>$a->n,'env_prefix'=>$a->z,'truthy'=>$a->A,'falsy'=>$a->B,'output'=>$a->h,'interactive'=>$a->d,'scroll'=>$a->C,'placeholder_fallback'=>$a->F,'width'=>$a->j,'legend'=>$a->G,'back_key'=>$a->r,];}protected function di(array $b):void{$a=$this;$a->s=$b['symbols_unicode'];$a->t=$b['symbols_ascii'];$a->q=$b['colors'];$a->b=$b['spacing'];$a->u=$b['labels'];$a->v=$b['unicode'];$a->n=$b['ansi'];$a->z=$b['env_prefix'];$a->A=$b['truthy'];$a->B=$b['falsy'];$a->h=$b['output'];$a->d=$b['interactive'];$a->C=$b['scroll'];$a->F=$b['placeholder_fallback'];$a->j=$b['width'];$a->G=$b['legend'];$a->r=$b['back_key'];$a->bc();}public static function configure(?array $symbols_unicode=NULL,?array $symbols_ascii=NULL,?array $colors=NULL,?array $spacing=NULL,?array $labels=NULL,?bool $unicode=NULL,?bool $ansi=NULL,?string $env_prefix=NULL,?array $truthy=NULL,?array $falsy=NULL,mixed $output=NULL,?bool $interactive=NULL,?int $scroll=NULL,?bool $placeholder_fallback=NULL,?int $width=NULL,?bool $legend=NULL,?string $back_key=NULL,):void{$e=$symbols_unicode;$f=$symbols_ascii;$c=$colors;$g=$spacing;$b=$labels;$m=$env_prefix;$h=$truthy;$i=$falsy;$n=$interactive;$j=$scroll;$o=$placeholder_fallback;$d=$width;$k=$back_key;$a=static::j();if($e!==NULL){$a->af('symbols_unicode',$e,$a->s);}if($f!==NULL){$a->af('symbols_ascii',$f,$a->t);}if($c!==NULL){$a->af('colors',$c,$a->q);}if($g!==NULL){$a->af('spacing',$g,$a->b);}if($b!==NULL){$a->af('labels',$b,$a->u);if(isset($b['step_total'])){$a->dj($b['step_total']);}}if($h!==NULL){$a->bs('truthy',$h);}if($i!==NULL){$a->bs('falsy',$i);}$l=$output!==NULL?$a->dk($output):NULL;if($j!==NULL){$a->bd($j,'scroll');}if($d!==NULL&&$d<0){throw new \InvalidArgumentException(sprintf('Width %d for "width" is not valid. Use 0 to detect the terminal width, or the number of columns to wrap to.',$d));}if($k!==NULL){$a->dl($k);}if($e!==NULL){$a->s=array_replace($a->s,$e);}if($f!==NULL){$a->t=array_replace($a->t,$f);}if($c!==NULL){$a->q=array_replace($a->q,$c);$a->e=array_replace($a->e,$c);}if($g!==NULL){$a->b=array_replace($a->b,$g);}if($b!==NULL){$a->u=array_replace($a->u,$b);}if($unicode!==NULL){$a->v=$unicode;}if($ansi!==NULL){$a->n=$ansi;}if($m!==NULL){$a->z=$m;}if($h!==NULL){$a->A=$h;}if($i!==NULL){$a->B=$i;}if($l!==NULL){$a->h=$l;}if($n!==NULL){$a->d=$n;}if($j!==NULL){$a->C=$j;}if($o!==NULL){$a->F=$o;}if($d!==NULL){$a->j=$d;}if($legend!==NULL){$a->G=$legend;}if($k!==NULL){$a->r=$k;}$a->bc();}public static function config():array{$a=static::j();return['symbols_unicode'=>$a->s,'symbols_ascii'=>$a->t,'symbols'=>$a->a,'colors'=>$a->e,'spacing'=>$a->b,'labels'=>$a->u,'unicode'=>$a->v,'ansi'=>$a->n,'env_prefix'=>$a->z,'truthy'=>$a->A,'falsy'=>$a->B,'output'=>$a->h,'interactive'=>$a->d,'scroll'=>$a->C,'placeholder_fallback'=>$a->F,'width'=>$a->j,'legend'=>$a->G,'back_key'=>$a->r,];}public static function results():array{return static::j()->c;}public static function version():string{return str_starts_with(static::VERSION,'__')?'development':static::VERSION;}public static function flow():PromptyFlow{return new PromptyFlow(static::dm(...));}public static function text(string $label,string $default='',string $placeholder='',string $description='',string $help='',mixed $discovered=NULL,?callable $transform=NULL,bool|string $required=FALSE,?callable $validate=NULL,?array $ctx=NULL,):?string{$h=$label;$i=$default;$l=$placeholder;$n=$description;$w=$transform;$x=$required;$y=$validate;$a=static::j();$ctx??=['depth'=>0,'is_last'=>FALSE,'open'=>[],];$o=!static::$k;$v=$ctx['results']??[];$p=$h;$i=$a->ag($ctx,$i,is_string(...));$i=$a->c($i);$l=$a->c($l);$s=$discovered??$ctx['discovered']??NULL;$g=NULL;if($s!==NULL||!$a->d){$g=trim($a->c((string)$s));if($g===''){$g=$i!==''?$i:$a->aq($l);}$g=$a->c($a->y($p,$w,$g));$a->ah($s!==NULL?'Discovered':'Default',$p,$x,$y,$g,$v);}if($o&&$a->d){$a->v();}$d=$ctx['depth']??0;$c=$ctx['open']??[];$h=$a->w($h,$ctx);if($g!==NULL){$c=$a->l($g,$c);$a->e($a->r($h,$g,$d,$c));if($o){$a->i();}return $g;}$t=function(string $e,string $f,?string $j)use($a,$h,$i,$l,$n,$help,$d,$c):array{$k=$a->bt($e,$f,$i,$l);if($d===0){$b=$a->k($h,'active','cyan');$b=array_merge($b,$n!==''?$a->o($n):[$a->a()]);$b=array_merge($b,$a->f($a->a().$a->b['indent'],$k,$a->a().$a->b['indent']));$b=array_merge($b,$a->s($j));$b=array_merge($b,$a->m($help));$b=array_merge($b,$a->p('text'));$b[]=$a->a();return $b;}$q=$a->h($d,$c);$b=$a->k($h,'active','cyan',$d,$c);$b=array_merge($b,$n!==''?$a->o($n,$d,$c):[$a->a().$q]);$b=array_merge($b,$a->f($a->a().$q,$k,$a->a().$q));$b=array_merge($b,$a->s($j,$d,$c));$b=array_merge($b,$a->m($help,$d,$c));$b=array_merge($b,$a->p('text',$d,$c));$b[]=$a->a().$q;return $b;};$e=$i;$f='';$j=NULL;$m=$a->e($t($e,$f,$j));while(TRUE){$r=$a->z();if($a->aa($r)){return NULL;}if($a->ab($r)){$a->g($m,$a->ai($h,$e.$f,$d,$c));if($o){$a->i();}return NULL;}if($r==='enter'){$u=$a->c($e.$f);$k=$u!==''?$u:$a->aq($l);$k=$a->c($a->y($p,$w,$k));$j=$a->ac($p,$x,$y,$k,$v);if($j===NULL){$a->aj($u);$c=$a->l($k,$c);$a->g($m,$a->r($h,$k,$d,$c));if($o){$a->i();}return $k;}$m=$a->g($m,$t($e,$f,$j));continue;}$z=$e.$f;[$e,$f]=$a->bu($r,$e,$f);if($e.$f!==$z){$j=NULL;}$m=$a->g($m,$t($e,$f,$j));}}public static function select(string $label,array $options=[],string $default='',string $description='',array $hints=[],?int $scroll=NULL,string $help='',mixed $discovered=NULL,?callable $transform=NULL,bool|string $required=FALSE,?callable $validate=NULL,?array $ctx=NULL,):?string{$e=$label;$h=$options;$x=$default;$s=$description;$H=$transform;$I=$required;$J=$validate;$a=static::j();$ctx??=['depth'=>0,'is_last'=>FALSE,'open'=>[],];$v=!static::$k;$w=$e;$a->be($e,$h);$x=$a->ag($ctx,$x,fn(mixed $F):bool=>is_string($F)&&array_key_exists($F,$h));$B=$x===''?NULL:$a->bf('Default',$e,$x,$h);$C=$discovered??$ctx['discovered']??NULL;$i=$C===NULL?NULL:$a->bf('Discovered',$e,$C,$h);$p=$a->ar($e,$scroll,count($h));$G=$ctx['results']??[];if($i===NULL&&!$a->d){$i=$B??$a->ak($h)[0];}if($i!==NULL){$i=$a->y($w,$H,$i);$a->ah($C!==NULL?'Discovered':'Default',$w,$I,$J,$i,$G);}if($v&&$a->d){$a->v();}$f=$ctx['depth']??0;$d=$ctx['open']??[];$e=$a->w($e,$ctx);$y=$a->ak($h);$n=array_map($a->c(...),array_values($h));$r=array_map(fn(string $k)=>$hints[$k]??'',$y);if($i!==NULL){$d=$a->l($i,$d);$a->e($a->r($e,$a->bg($i,$h),$f,$d));if($v){$a->i();}return $i;}$D=function(int $c,int $l,?string $m)use($a,$e,$n,$s,$r,$help,$p,$f,$d):array{$K=array_slice($n,$l,$p,TRUE);$j=[];if($f===0){$b=$a->k($e,'active','cyan');$b=array_merge($b,$s!==''?$a->o($s):[$a->a()]);foreach($K as $o=>$t){$g=$o===$c;$u=$a->b($a->a[$g?'radio_on':'radio_off'],$g?'green':'dim');$E=$g?$t:$a->b($t,'dim');$j=array_merge($j,$a->f($a->a().$a->q($g).$u.' ',$E,$a->a().$a->t($a->q(FALSE).$u.' ')));if($g&&($r[$o]??'')!==''){$j=array_merge($j,$a->at($r[$o]));}}$b=array_merge($b,$a->ad($j,$l,$p,count($n)));$b=array_merge($b,$a->s($m));$b=array_merge($b,$a->m($help));$b=array_merge($b,$a->p('select'));$b[]=$a->a();return $b;}$z=$a->h($f,$d);$b=$a->k($e,'active','cyan',$f,$d);$b=array_merge($b,$s!==''?$a->o($s,$f,$d):[$a->a().$z]);foreach($K as $o=>$t){$g=$o===$c;$u=$a->b($a->a[$g?'radio_on':'radio_off'],$g?'green':'dim');$E=$g?$t:$a->b($t,'dim');$j=array_merge($j,$a->f($a->a().$z.$a->q($g).$u.' ',$E,$a->a().$z.$a->t($a->q(FALSE).$u.' ')));if($g&&($r[$o]??'')!==''){$j=array_merge($j,$a->at($r[$o],$f,$d));}}$b=array_merge($b,$a->ad($j,$l,$p,count($n),$f,$d));$b=array_merge($b,$a->s($m,$f,$d));$b=array_merge($b,$a->m($help,$f,$d));$b=array_merge($b,$a->p('select',$f,$d));$b[]=$a->a().$z;return $b;};$c=0;if($B!==NULL){$c=(int)array_search($B,$y,TRUE);}$m=NULL;$l=$a->al(0,$c,$p);$q=$a->e($D($c,$l,$m));while(TRUE){$k=$a->z();if($a->aa($k)){return NULL;}if($a->ab($k)){$a->g($q,$a->ai($e,$n[$c],$f,$d));if($v){$a->i();}return NULL;}if($k==='enter'){$A=$a->y($w,$H,$y[$c]);$m=$a->ac($w,$I,$J,$A,$G);if($m===NULL){$a->aj($y[$c]);$d=$a->l($A,$d);$a->g($q,$a->r($e,$a->bg($A,$h),$f,$d));if($v){$a->i();}return $A;}$q=$a->g($q,$D($c,$l,$m));continue;}$L=$c;if($k==='up'||$k==='left'){$c=($c-1+count($n))%count($n);}elseif($k==='down'||$k==='right'){$c=($c+1)%count($n);}if($c!==$L){$m=NULL;}$l=$a->al($l,$c,$p);$q=$a->g($q,$D($c,$l,$m));}}public static function multiselect(string $label,array $options=[],array $default=[],string $description='',array $hints=[],?int $scroll=NULL,string|\Closure $order='options',string $help='',mixed $discovered=NULL,?callable $transform=NULL,bool|string $required=FALSE,?callable $validate=NULL,?array $ctx=NULL,):?array{$f=$label;$h=$options;$G=$default;$y=$description;$H=$order;$L=$transform;$M=$required;$N=$validate;$a=static::j();$ctx??=['depth'=>0,'is_last'=>FALSE,'open'=>[],];$B=!static::$k;$p=$f;$u=$discovered??$ctx['discovered']??NULL;if(is_scalar($u)){$u=explode(',',(string)$u);}$a->be($f,$h);$G=$a->ag($ctx,$G,fn(mixed $C):bool=>is_array($C)&&array_is_list($C)&&$C===array_values(array_filter($C,fn(mixed $e):bool=>is_string($e)&&array_key_exists($e,$h))));$q=$a->bv('Default',$f,$G,$h);$i=$u===NULL?NULL:$a->bv('Discovered',$f,$u,$h);$r=$a->ar($f,$scroll,count($h));$a->dn($H,$f);$K=$ctx['results']??[];if($i===NULL&&!$a->d){$i=$q;}if($i!==NULL){$i=$a->bh($p,$i,$H,$h,$q);$i=$a->bw($p,$L,$i);$a->ah($u!==NULL?'Discovered':'Default',$p,$M,$N,$i,$K);}if($B&&$a->d){$a->v();}$g=$ctx['depth']??0;$d=$ctx['open']??[];$f=$a->w($f,$ctx);$v=$a->ak($h);$s=array_map($a->c(...),array_values($h));$x=array_map(fn(string $e)=>$hints[$e]??'',$v);if($i!==NULL){$d=$a->l($i,$d);$a->e($a->r($f,$a->bx($i,$h),$g,$d));if($B){$a->i();}return $i;}$I=function(int $c,array $j,int $l,?string $m)use($a,$f,$s,$y,$x,$help,$r,$g,$d):array{$O=array_slice($s,$l,$r,TRUE);$k=[];if($g===0){$b=$a->k($f,'active','cyan');$b=array_merge($b,$y!==''?$a->o($y):[$a->a()]);foreach($O as $n=>$z){$t=$j[$n]??FALSE;$o=$n===$c;$A=$a->b($a->a[$t?'check_on':'check_off'],$o||$t?'green':'dim');$J=$o||$t?$z:$a->b($z,'dim');$k=array_merge($k,$a->f($a->a().$a->q($o).$A.' ',$J,$a->a().$a->t($a->q(FALSE).$A.' ')));if($o&&($x[$n]??'')!==''){$k=array_merge($k,$a->at($x[$n]));}}$b=array_merge($b,$a->ad($k,$l,$r,count($s)));$b=array_merge($b,$a->s($m));$b=array_merge($b,$a->m($help));$b=array_merge($b,$a->p('multiselect'));$b[]=$a->a();return $b;}$D=$a->h($g,$d);$b=$a->k($f,'active','cyan',$g,$d);$b=array_merge($b,$y!==''?$a->o($y,$g,$d):[$a->a().$D]);foreach($O as $n=>$z){$t=$j[$n]??FALSE;$o=$n===$c;$A=$a->b($a->a[$t?'check_on':'check_off'],$o||$t?'green':'dim');$J=$o||$t?$z:$a->b($z,'dim');$k=array_merge($k,$a->f($a->a().$D.$a->q($o).$A.' ',$J,$a->a().$D.$a->t($a->q(FALSE).$A.' ')));if($o&&($x[$n]??'')!==''){$k=array_merge($k,$a->at($x[$n],$g,$d));}}$b=array_merge($b,$a->ad($k,$l,$r,count($s),$g,$d));$b=array_merge($b,$a->s($m,$g,$d));$b=array_merge($b,$a->m($help,$g,$d));$b=array_merge($b,$a->p('multiselect',$g,$d));$b[]=$a->a().$D;return $b;};$c=0;if($r<count($v)&&$q!==[]){$c=(int)array_search($a->bh($p,$q,'options',$h,[])[0],$v,TRUE);}$j=array_map(fn(string $e):bool=>in_array($e,$q,TRUE),$v);$E=$q;$m=NULL;$l=$a->al(0,$c,$r);$w=$a->e($I($c,$j,$l,$m));while(TRUE){$e=$a->z();if($a->aa($e)){return NULL;}if($a->ab($e)){$a->g($w,$a->ai($f,'',$g,$d));if($B){$a->i();}return NULL;}if($e==='enter'){$P=$a->bh($p,$E,$H,$h,$q);$F=$a->bw($p,$L,$P);$m=$a->ac($p,$M,$N,$F,$K);if($m===NULL){$a->aj($P);$d=$a->l($F,$d);$a->g($w,$a->r($f,$a->bx($F,$h),$g,$d));if($B){$a->i();}return $F;}$w=$a->g($w,$I($c,$j,$l,$m));continue;}$Q=$j;if($e==='space'){$j[$c]=!$j[$c];$E=array_values(array_diff($E,[$v[$c]]));if($j[$c]){$E[]=$v[$c];}}elseif($e==='up'||$e==='left'){$c=($c-1+count($s))%count($s);}elseif($e==='down'||$e==='right'){$c=($c+1)%count($s);}if($j!==$Q){$m=NULL;}$l=$a->al($l,$c,$r);$w=$a->g($w,$I($c,$j,$l,$m));}}public static function suggest(string $label,array|\Closure $options=[],string $default='',string $placeholder='',string $description='',?int $scroll=NULL,string $help='',mixed $discovered=NULL,?callable $transform=NULL,bool|string $required=FALSE,?callable $validate=NULL,?array $ctx=NULL,):?string{$f=$label;$x=$options;$l=$default;$q=$placeholder;$u=$description;$z=$scroll;$D=$transform;$E=$required;$F=$validate;$a=static::j();$ctx??=['depth'=>0,'is_last'=>FALSE,'open'=>[],];$v=!static::$k;$C=$ctx['results']??[];$w=$f;if(is_array($x)){$a->be($f,$x);}$a->bd($z??$a->C,$f);$l=$a->ag($ctx,$l,is_string(...));$l=$a->c($l);$q=$a->c($q);$A=$discovered??$ctx['discovered']??NULL;$j=NULL;if($A!==NULL||!$a->d){$j=trim($a->c((string)$A));if($j===''){$j=$l!==''?$l:$a->aq($q);}$j=$a->c($a->y($w,$D,$j));$a->ah($A!==NULL?'Discovered':'Default',$w,$E,$F,$j,$C);}if($v&&$a->d){$a->v();}$g=$ctx['depth']??0;$e=$ctx['open']??[];$f=$a->w($f,$ctx);if($j!==NULL){$e=$a->l($j,$e);$a->e($a->r($f,$j,$g,$e));if($v){$a->i();}return $j;}$B=function(string $h,string $i,array $d,?int $c,int $k,int $m,?string $n)use($a,$f,$l,$q,$u,$help,$g,$e):array{$o=$a->bt($h,$i,$l,$q);$r=$g>0?$a->h($g,$e):'';$y=[];foreach(array_slice($d,$k,$m,TRUE)as $J=>$G){$H=$J===$c;$K=$a->a().$r.$a->q($H);$y=array_merge($y,$a->f($K,$H?$G:$a->b($G,'dim'),$a->a().$r.$a->t($a->q(FALSE))));}if($g===0){$b=$a->k($f,'active','cyan');$b=array_merge($b,$u!==''?$a->o($u):[$a->a()]);$b=array_merge($b,$a->f($a->a().$a->b['indent'],$o,$a->a().$a->b['indent']));$b=array_merge($b,$a->ad($y,$k,$m,count($d)));$b=array_merge($b,$a->s($n));$b=array_merge($b,$a->m($help));$b=array_merge($b,$a->p('suggest'));$b[]=$a->a();return $b;}$b=$a->k($f,'active','cyan',$g,$e);$b=array_merge($b,$u!==''?$a->o($u,$g,$e):[$a->a().$r]);$b=array_merge($b,$a->f($a->a().$r,$o,$a->a().$r));$b=array_merge($b,$a->ad($y,$k,$m,count($d),$g,$e));$b=array_merge($b,$a->s($n,$g,$e));$b=array_merge($b,$a->m($help,$g,$e));$b=array_merge($b,$a->p('suggest',$g,$e));$b[]=$a->a().$r;return $b;};$h=$l;$i='';$d=$a->by($x,$h);$m=$a->ar($f,$z,count($d));$c=NULL;$k=0;$n=NULL;$s=$a->e($B($h,$i,$d,$c,$k,$m,$n));while(TRUE){$t=$a->z();if($a->aa($t)){return NULL;}if($a->ab($t)){$a->g($s,$a->ai($f,$c!==NULL?$d[$c]:$h.$i,$g,$e));if($v){$a->i();}return NULL;}if($t==='enter'){$p=$a->c($h.$i);$o=$c!==NULL?$d[$c]:($p!==''?$p:$a->aq($q));$o=$a->c($a->y($w,$D,$o));$n=$a->ac($w,$E,$F,$o,$C);if($n===NULL){$a->aj($c!==NULL?$d[$c]:$p);$e=$a->l($o,$e);$a->g($s,$a->r($f,$o,$g,$e));if($v){$a->i();}return $o;}$s=$a->g($s,$B($h,$i,$d,$c,$k,$m,$n));continue;}$I=$h.$i;$L=$c;if($t==='down'&&$d!==[]){$c=$c===NULL?0:($c+1<count($d)?$c+1:NULL);}elseif($t==='up'&&$d!==[]){$c=$c===NULL?count($d)-1:($c>0?$c-1:NULL);}else{[$h,$i]=$a->bu($t,$h,$i);}$p=$h.$i;if($p!==$I){$d=$a->by($x,$p);$m=$a->ar($f,$z,count($d));$c=NULL;$k=0;}elseif($c!==NULL){$k=$a->al($k,$c,$m);}if($p!==$I||$c!==$L){$n=NULL;}$s=$a->g($s,$B($h,$i,$d,$c,$k,$m,$n));}}public static function confirm(string $label,bool $default=TRUE,string $description='',string $help='',mixed $discovered=NULL,?callable $transform=NULL,bool|string $required=FALSE,?callable $validate=NULL,?array $ctx=NULL,):?bool{$f=$label;$n=$default;$k=$description;$t=$transform;$u=$required;$v=$validate;$a=static::j();$ctx??=['depth'=>0,'is_last'=>FALSE,'open'=>[],];$l=!static::$k;$m=$f;$x=$ctx['truthy']??$a->A;$y=$ctx['falsy']??$a->B;$n=$a->ag($ctx,$n,is_bool(...));$q=$discovered??$ctx['discovered']??NULL;$g=$q===NULL?NULL:$a->dp($f,$q,$x,$y);$s=$ctx['results']??[];if($g===NULL&&!$a->d){$g=$n;}if($g!==NULL){$g=$a->bz($m,$t,$g);$a->ah($q!==NULL?'Discovered':'Default',$m,$u,$v,$g,$s);}if($l&&$a->d){$a->v();}$e=$ctx['depth']??0;$c=$ctx['open']??[];$f=$a->w($f,$ctx);if($g!==NULL){$c=$a->l($g,$c);$a->e($a->r($f,$g?$a->d('yes'):$a->d('no'),$e,$c));if($l){$a->i();}return $g;}$r=function(bool $z,?string $h)use($a,$f,$k,$help,$e,$c):array{$w=$z?$a->b($a->a['radio_on'],'green').' '.$a->d('yes').' '.$a->b($a->d('separator'),'dim').' '.$a->b($a->a['radio_off'],'dim').' '.$a->b($a->d('no'),'dim'):$a->b($a->a['radio_off'],'dim').' '.$a->b($a->d('yes'),'dim').' '.$a->b($a->d('separator'),'dim').' '.$a->b($a->a['radio_on'],'green').' '.$a->d('no');if($e===0){$b=$a->k($f,'active','cyan');$b=array_merge($b,$k!==''?$a->o($k):[$a->a()]);$b=array_merge($b,$a->f($a->a().$a->b['indent'],$w,$a->a().$a->b['indent']));$b=array_merge($b,$a->s($h));$b=array_merge($b,$a->m($help));$b=array_merge($b,$a->p('confirm'));$b[]=$a->a();return $b;}$o=$a->h($e,$c);$b=$a->k($f,'active','cyan',$e,$c);$b=array_merge($b,$k!==''?$a->o($k,$e,$c):[$a->a().$o]);$b=array_merge($b,$a->f($a->a().$o,$w,$a->a().$o));$b=array_merge($b,$a->s($h,$e,$c));$b=array_merge($b,$a->m($help,$e,$c));$b=array_merge($b,$a->p('confirm',$e,$c));$b[]=$a->a().$o;return $b;};$d=$n;$h=NULL;$j=$a->e($r($d,$h));while(TRUE){$i=$a->z();if($a->aa($i)){return NULL;}if($a->ab($i)){$a->g($j,$a->ai($f,$d?$a->d('yes'):$a->d('no'),$e,$c));if($l){$a->i();}return NULL;}if($i==='enter'){$p=$a->bz($m,$t,$d);$h=$a->ac($m,$u,$v,$p,$s);if($h===NULL){$a->aj($d);$c=$a->l($p,$c);$a->g($j,$a->r($f,$p?$a->d('yes'):$a->d('no'),$e,$c));if($l){$a->i();}return $p;}$j=$a->g($j,$r($d,$h));continue;}$A=$d;if(in_array($i,['left','right','up','down','tab'],TRUE)){$d=!$d;}elseif($i==='y'||$i==='Y'){$d=TRUE;}elseif($i==='n'||$i==='N'){$d=FALSE;}if($d!==$A){$h=NULL;}$j=$a->g($j,$r($d,$h));}}public static function pause(string $label,string $description='',string $help='',bool $enter_only=FALSE,bool $erase=FALSE,?array $ctx=NULL,):?bool{$e=$label;$l=$description;$m=$enter_only;$a=static::j();$ctx??=['depth'=>0,'is_last'=>FALSE,'open'=>[],];$i=!static::$k;$b=$ctx['depth']??0;$c=$ctx['open']??[];$e=$a->w($e,$ctx);$j=function(int $f)use($a,$e,$erase,$b,$c):bool{$c=$a->l(TRUE,$c);if($erase){$a->au($f);return TRUE;}$k=$a->k($e,'completed','cyan',$b,$c);$k[]=$a->a().($b>0?$a->h($b,$c):'');$a->g($f,$k);return TRUE;};if(!$a->d){return $j(0);}if($i){$a->v();}$h=$b>0?$a->h($b,$c):'';$n=$a->a().($b>0?$h:$a->b['indent']);$d=$a->k($e,'active','cyan',$b,$c);$d=array_merge($d,$l!==''?array_slice($a->o($l,$b,$c),0,-1):[]);$d=array_merge($d,$a->m($help,$b,$c));$d=array_merge($d,$a->p($m?'pause_enter':'pause',$b,$c));$d[]=$a->a().$h;$f=$a->e($d);try{while(TRUE){$g=$a->z(is_asking:FALSE);if($a->aa($g)){return NULL;}if($a->ab($g)){$a->g($f,[...$a->k($e,'active','red',$b,$c),$n.$a->b($a->d('cancelled'),'red'),$a->a().$h,]);return NULL;}if($g==='enter'||!$m){$a->dq($g);return $j($f);}}}finally{if($i){$a->i();}}}public static function task(string $label,callable $action,string|callable $hint='',string|callable $success='',string|callable $failure='',bool $stream=FALSE,?array $ctx=NULL,):mixed{$c=$label;$a=static::j();$ctx??=['depth'=>0,'is_last'=>FALSE,'open'=>[],];$i=!static::$k;$g=$c;$j=$ctx['depth']??0;$e=$ctx['open']??[];$c=$a->w($c,$ctx);$f=$a->dr()&&!$a->bi();$k=function(mixed $b)use($a,$g,$hint,$success,$failure,$e):array{$d=$b!==FALSE;$l=$a->ca($g,$d?'success':'failure',$d?$success:$failure,$b);return['ok'=>$d,'hint'=>$a->ca($g,'hint',$hint,$b),'details'=>is_array($b)&&array_is_list($b)&&count(array_filter($b,is_string(...)))===count($b)?$b:[],'message'=>$l!==''?$l:$a->d($d?'done':'failed'),'open'=>$a->l($d,$e),];};$m=$a->h;if($f&&$i){$a->cb();}$h=$a->l;if($h!==NULL){$a->cc($h);}try{return $stream?$a->ds($c,$action,$k,$j,$e,$f):$a->dt($c,$action,$k,$j,$e,$f);}finally{if($h!==NULL){$a->cd();}if($f&&$i){$a->ae($m,$a->ce(...));}}}public static function note(string $message,string $type='info'):void{$a=static::j();$a->cf($type);$a->e($a->cg($message,$type));}public static function intro(string $message):void{$a=static::j();$a->e($a->ch($message));}public static function outro(string $message):void{$a=static::j();$a->e($a->bj($message));}public static function table(array $rows,array $headers=[]):void{$a=static::j();$b=$a->du($rows,$headers);if($b===[]){return;}$a->e($b);}public static function box(string $content,string $title=''):void{$a=static::j();$a->e($a->dv($content,$title));}public static function output(array $lines):int{static::j()->e($lines);return count($lines);}public static function style(string $text,string...$styles):string{$a=static::j();$b='';foreach($styles as $d){if($d==='reset'||!array_key_exists($d,$a->e)){$f=array_diff(array_keys($a->e),['reset']);throw new \InvalidArgumentException(sprintf('Style "%s" is not valid. Available styles: %s.',$d,implode(', ',$f)));}$b.=$a->e[$d];}if($b===''){return $text;}$g=array_map(function(string $c)use($a,$b):string{if($c===''){return'';}$c=preg_replace_callback('/\033\[[0-9;:]*m/',fn(array $e):string=>$e[0].($a->ci($e[0])?$b:''),$c)??$c;return $b.$c.$a->e['reset'];},explode("\n",$text));return implode("\n",$g);}public static function strip(string $text):string{return static::j()->cj($text);}public static function width(string $text):int{$a=static::j();return max(array_map($a->n(...),explode("\n",$text)));}public static function terminalWidth():int{$a=static::j();return $a->j>0?$a->j:$a->ck();}protected function af(string $c,array $d,array $a):void{foreach(array_keys($d)as $b){if(!array_key_exists($b,$a)){throw new \InvalidArgumentException(sprintf('Configuration key "%s" for "%s" is not valid. Available keys: %s.',$b,$c,implode(', ',array_keys($a))));}}}protected function bs(string $a,array $b):void{if($b===[]){throw new \InvalidArgumentException(sprintf('No values declared for "%s". Provide at least one value.',$a));}foreach($b as $c){if(trim($c)===''){throw new \InvalidArgumentException(sprintf('Blank value declared for "%s". Every value must hold a non-space character.',$a));}}}protected function dj(string $a):void{try{sprintf($a,1,1);}catch(\ValueError|\ArgumentCountError $b){throw new \InvalidArgumentException(sprintf('Format "%s" for the "step_total" label is not valid (%s). Use a format that takes the step number and the total, such as "(%%d/%%d)".',$a,$b->getMessage()),0,$b);}}protected function dl(string $a):void{if($a===''){return;}if(preg_match('/^ctrl-[a-z]$/',$a)!==1||in_array($a,static::RESERVED_CTRL_KEYS,TRUE)){throw new \InvalidArgumentException(sprintf('Back key "%s" for "back_key" is not valid. Use a Ctrl key from "ctrl-a" to "ctrl-z" other than "ctrl-c", "ctrl-h", "ctrl-i", "ctrl-j" and "ctrl-m", or "" to turn stepping back off.',$a));}}protected function cf(string $a):void{if(!in_array($a,static::NOTE_TYPES,TRUE)){throw new \InvalidArgumentException(sprintf('Note type "%s" is not valid. Use 1 of: %s.',$a,implode(', ',static::NOTE_TYPES)));}}protected function bd(int $a,string $b):void{if($a<0){throw new \InvalidArgumentException(sprintf('Scroll size %d for "%s" is not valid. Use 0 to show every option, or the number of options to show at once.',$a,$b));}}protected function dk(mixed $a){if(is_callable($a)){return $a(...);}if(!is_resource($a)||get_resource_type($a)!=='stream'){throw new \InvalidArgumentException(sprintf('Output target of type "%s" for "output" is not valid. Provide a writable stream or a callable that receives each chunk.',get_debug_type($a)));}$b=stream_get_meta_data($a)['mode'];if(strpbrk($b,'waxc+')===FALSE){throw new \InvalidArgumentException(sprintf('Output stream for "output" is opened in read-only mode "%s". Provide a writable stream.',$b));}return $a;}protected function be(string $a,array $b):void{if($b===[]){throw new \InvalidArgumentException(sprintf('No options declared for "%s". Provide at least one option.',$a));}}protected function bf(string $d,string $e,mixed $a,array $b):string{$c=is_scalar($a)?trim((string)$a):get_debug_type($a);if(!array_key_exists($c,$b)){$f=$b===[]?'none':implode(', ',$this->ak($b));throw new \InvalidArgumentException(sprintf('%s value "%s" for "%s" is not a valid option. Available options: %s.',$d,$c,$e,$f));}return $c;}protected function bv(string $d,string $e,mixed $a,array $f):array{$g=is_array($a)?$a:[$a];$c=[];foreach($g as $b){if(is_scalar($b)&&trim((string)$b)===''){continue;}$c[]=$this->bf($d,$e,$b,$f);}return array_values(array_unique($c));}protected function dn(string|\Closure $a,string $b):void{if(is_string($a)&&!in_array($a,['options','default','selection'],TRUE)){throw new \InvalidArgumentException(sprintf('Order "%s" for "%s" is not valid. Use "options", "default" or "selection".',$a,$b));}}protected function bh(string $e,array $a,string|\Closure $c,array $h,array $f):array{if($c instanceof \Closure){usort($a,function(string $i,string $j)use($e,$c):int{$d=$c($i,$j);if(!is_int($d)){$this->i();throw new \UnexpectedValueException(sprintf('Order for "%s" returned %s. An order closure compares 2 option keys and returns an int, as for usort().',$e,get_debug_type($d)));}return $d;});return $a;}$g=array_values(array_filter($this->ak($h),fn(string $b):bool=>in_array($b,$a,TRUE)));return match($c){'selection'=>$a,'default'=>[...array_values(array_filter($f,fn(string $b):bool=>in_array($b,$a,TRUE))),...array_values(array_filter($g,fn(string $b):bool=>!in_array($b,$f,TRUE))),],default=>$g,};}protected function ak(array $a):array{return array_map(strval(...),array_keys($a));}protected function bg(string $a,array $b):string{return array_key_exists($a,$b)?$b[$a]:$a;}protected function bx(array $a,array $b):string{if($a===[]){return $this->d('none');}return implode(', ',array_map(fn(string $c):string=>$this->bg($c,$b),$a));}protected function ar(string $c,?int $a,int $b):int{$a??=$this->C;$this->bd($a,$c);return $a===0?$b:min($a,$b);}protected function al(int $b,int $a,int $c):int{if($a<$b){return $a;}if($a>=$b+$c){return $a-$c+1;}return $b;}protected function by(array|\Closure $b,string $a):array{$a=$this->c($a);if($b instanceof \Closure){$d=$b($a);return is_array($d)?array_values(array_map(fn(string|int|float|bool $h):string=>$this->c((string)$h),array_filter($d,is_scalar(...)))):[];}$e=array_values(array_map($this->c(...),$b));if($a===''){return $e;}$c=preg_match('//u',$a)===1?'/'.preg_quote($a,'/').'/iu':NULL;return array_values(array_filter($e,function(string $f)use($c,$a):bool{$g=$c===NULL?FALSE:preg_match($c,$f);return $g===FALSE?stripos($f,$a)!==FALSE:$g===1;}));}protected function dp(string $g,mixed $a,array $c,array $d):bool{if(is_bool($a)){return $a;}$e=is_scalar($a)?trim((string)$a):get_debug_type($a);$b=fn(string $h):string=>strtolower(trim($h));$f=$b($e);if(in_array($f,array_map($b,$c),TRUE)){return TRUE;}if(in_array($f,array_map($b,$d),TRUE)){return FALSE;}$i=implode(', ',array_merge($c,$d));throw new \InvalidArgumentException(sprintf('Discovered value "%s" for "%s" is not a valid answer. Accepted values: %s.',$e,$g,$i));}protected function aq(string $a):string{return $this->d&&$this->F?$a:'';}protected function y(string $d,?callable $b,string $c):string{if($b===NULL){return $c;}$a=$this->bk($b,$c);if(!is_string($a)){$this->av($d,get_debug_type($a),'a string');}return $a;}protected function bw(string $c,?callable $d,array $e):array{if($d===NULL){return $e;}$a=$this->bk($d,$e);if(!is_array($a)||!array_is_list($a)){$this->av($c,get_debug_type($a),'a list of strings');}$f=[];foreach($a as $b){if(!is_string($b)){$this->av($c,'a list holding '.get_debug_type($b),'a list of strings');}$f[]=$b;}return $f;}protected function bz(string $d,?callable $b,bool $c):bool{if($b===NULL){return $c;}$a=$this->bk($b,$c);if(!is_bool($a)){$this->av($d,get_debug_type($a),'a boolean');}return $a;}protected function bk(callable $a,string|bool|array $b):mixed{try{return $a($b);}catch(\Throwable $c){$this->i();throw $c;}}protected function av(string $a,string $b,string $c):never{$this->i();throw new \UnexpectedValueException(sprintf('Transform for "%s" returned %s. A transform returns %s, the type the widget answers with.',$a,$b,$c));}protected function ac(string $e,bool|string $c,?callable $f,string|bool|array $b,array $h):?string{$d=$this;$g=is_array($b)?$b===[]:is_string($b)&&trim($b)==='';if(($c===FALSE||!$g)&&$f===NULL){return NULL;}try{if($c!==FALSE&&$g){$a=is_string($c)&&trim($d->c($c))!==''?trim($c):$d->d('required');}else{$a=$f($b,$h);if($a!==NULL&&!is_string($a)){throw new \UnexpectedValueException(sprintf('Validator for "%s" returned %s. A validator returns NULL for a valid answer or an error message string.',$e,get_debug_type($a)));}$a=$a===NULL||trim($d->c($a))===''?NULL:trim($a);}if($a!==NULL&&static::$P instanceof \Closure){(static::$P)($e,$b,$a);}}catch(\Throwable $i){$d->i();throw $i;}return $a;}protected function ah(string $d,string $b,bool|string $e,?callable $f,string|bool|array $a,array $g):void{$c=$this->ac($b,$e,$f,$a,$g);if($c===NULL){return;}$h=match(TRUE){is_bool($a)=>$a?'true':'false',is_array($a)=>implode(',',$a),default=>$a,};throw new \InvalidArgumentException(sprintf('%s value "%s" for "%s" is not valid. %s',$d,$h,$b,$c));}protected function z(bool $is_asking=TRUE):string{$b=$this;$c=array_key_last($b->p);if($is_asking&&$c!==NULL){$b->p[$c]['asked']=TRUE;}$d=$b->M??STDIN;$a=fread($d,1);if($a===FALSE||$a===''){return'eof';}return match(TRUE){$a==="\x03"=>'ctrl-c',$a==="\n",$a==="\r"=>'enter',$a==="\x7f",$a==="\x08"=>'backspace',$a==="\t"=>'tab',$a===' '=>'space',$a==="\x1b"=>$b->dw($d),ord($a)>=0x01&&ord($a)<=0x1A=>'ctrl-'.chr(ord($a)+0x60),default=>$a,};}protected function dw($c):string{$b=$this->cl($c);if($b!=='['&&$b!=='O'){return'escape';}$d='';do{$a=$this->cl($c);if($a===''){return'escape';}$d.=$a;}while($b==='['&&ord($a)>=0x20&&ord($a)<=0x3F);return match($d){'A'=>'up','B'=>'down','C'=>'right','D'=>'left','H','1~','7~'=>'home','F','4~','8~'=>'end','3~'=>'delete',default=>'unknown',};}protected function cl($a):string{$b=[$a];$c=NULL;$d=NULL;if(!stream_get_meta_data($a)['seekable']&&stream_select($b,$c,$d,0,static::ESCAPE_TIMEOUT)===0){return'';}return(string)fread($a,1);}protected function dq(string $b):void{if(strlen($b)!==1){return;}$a=ord($b);$c=match(TRUE){$a>=0xF8=>0,$a>=0xF0=>3,$a>=0xE0=>2,$a>=0xC0=>1,default=>0,};if($c>0){fread($this->M??STDIN,$c);}}protected function ab(string $a):bool{return in_array($a,['ctrl-c','escape','eof'],TRUE);}protected function bu(string $b,string $a,string $c):array{$d=$this->dx($a);$e=$this->cm($c);return match(TRUE){$b==='left'=>[substr($a,0,strlen($a)-strlen($d)),$d.$c],$b==='right'=>[$a.$e,substr($c,strlen($e))],$b==='home'=>['',$a.$c],$b==='end'=>[$a.$c,''],$b==='backspace'=>[substr($a,0,strlen($a)-strlen($d)),$c],$b==='delete'=>[$a,substr($c,strlen($e))],$b==='space'=>[$a.' ',$c],strlen($b)===1&&ord($b)>=32=>[$a.$b,$c],default=>[$a,$c],};}protected function aa(string $b):bool{$a=$this;if($a->r===''||$b!==$a->r||$a->m===NULL){return FALSE;}$a->y=$a->cn();return $a->y!==NULL;}protected function cn():?int{$b=$this->p;$d=array_pop($b);$e=$d['depth']??0;$c=NULL;for($a=count($b)-1;$a>=0;$a--){if(!$b[$a]['asked']){continue;}if($b[$a]['depth']<=$e){return $a;}$c??=$a;}return $c;}protected function dy(int $b):void{$a=$this;$c=$a->p[$b];$a->E=array_replace($a->E,array_filter($a->c,fn(mixed $d):bool=>$d!==NULL));$a->au($a->D-$c['rows']);$a->p=array_slice($a->p,0,$b);$a->x=$c['key'];$a->y=NULL;}protected function co():void{$a=$this;$a->D=0;$a->p=[];$a->E=[];$a->J=[];$a->x=NULL;$a->y=NULL;}protected function aj(mixed $a):void{if($this->m!==NULL){$this->J[(string)$this->m['key']]=$a;}}protected function ag(array $ctx,mixed $a,\Closure $b):mixed{if(!array_key_exists('previous',$ctx)||!$b($ctx['previous'])){return $a;}return $ctx['previous'];}protected function v():void{$a=$this;$a->l=$a->M===NULL?(shell_exec('stty -g 2>'.static::NULL_DEVICE)?:NULL):NULL;if($a->l!==NULL){$a->l=trim($a->l);$a->cd();$a->cb();}}protected function cd():void{shell_exec('stty -echo -icanon -isig -ixon -iexten min 1 time 0 2>'.static::NULL_DEVICE);}protected function i():void{$a=$this;if($a->l!==NULL){$a->cc($a->l);$a->ce();$a->l=NULL;}}protected function cc(string $a):void{shell_exec('stty '.$a.' 2>'.static::NULL_DEVICE);}protected function ce():void{$this->aw("\033[?25h");}protected function cb():void{$this->aw("\033[?25l");}protected function dr():bool{$a=$this;if(!$a->d||!$a->n){return FALSE;}if($a->S!==NULL){return $a->S;}if($a->h instanceof \Closure){return FALSE;}return stream_isatty($a->h??STDOUT);}protected function ca(string $c,string $d,string|callable $a,mixed $e):string{if(is_string($a)){return $a;}$b=$a($e);if(!is_string($b)){throw new \UnexpectedValueException(sprintf('The %s callable for task "%s" returned %s. It must return the text to draw.',$d,$c,get_debug_type($b)));}return $b;}protected function dt(string $e,callable $o,\Closure $p,int $d,array $g,bool $c):mixed{$a=$this;$h=$a->h;$i=preg_split('//u',$a->a['spinner'],-1,PREG_SPLIT_NO_EMPTY)?:[$a->a['spinner']];$j=fn(int $q):array=>$a->bl($e,$a->b($i[$q%count($i)],'cyan'),$d,$g);$f=$a->e($c?$j(0):$a->k($e,'active','cyan',$d,$g));$k=$c?$a->dz($j,$f):NULL;try{$l=$o();$b=$p($l);}catch(\Throwable $r){$a->cp($k);if($c){$a->ae($h,fn()=>$a->au($f));}throw $r;}$a->cp($k);$m=$a->cq($b['ok'],$b['hint'],$b['details'],$b['message'],'',$d,$b['open']);$n=$c?[...$a->cr($e,$b['ok'],$d,$b['open']),...$m]:$m;$a->ae($h,fn():int=>$c?$a->g($f,$n):$a->e($n));return $l;}protected function ds(string $d,callable $q,\Closure $r,int $b,array $c,bool $e):mixed{$a=$this;$i=$a->h;$f=$a->e($a->k($d,'active','cyan',$b,$c));$j=count($a->f);$s=$e?function()use($j,$d,$b,$c,&$f):void{$f=$this->g($f,array_merge($this->k($d,'active','cyan',$b,$c),$this->ea($this->f[$j],$b,$c)));}:function(string $t)use($b,$c):void{$this->e($this->bm($t,$b,$c));};$a->f[]=['lines'=>[],'partial'=>'','is_live'=>$e,'draw'=>$s,'output'=>$i];$u=ob_get_level();ob_start(fn(string $v):string=>$a->eb($j,$v),1);$g=[];try{try{$m=$q();}finally{$g=$a->ec($j,$u);}$h=$r($m);}catch(\Throwable $w){if($e){$x=array_merge($a->k($d,'active','red',$b,$c),$a->cs($g,$b,$c));$a->ae($i,fn():int=>$a->g($f,$x));}throw $w;}$k=$h['ok'];$l=$h['open'];$p=$g!==[]?[$a->a().($b>0?$a->h($b,$l):'')]:[];$n=count($g);$y=$e&&$k&&$n>0?sprintf(' (%d %s)',$n,$a->d($n===1?'line':'lines')):'';$o=$a->cq($k,$h['hint'],$h['details'],$h['message'],$y,$b,$l);if(!$e){$a->ae($i,fn():int=>$a->e(array_merge($p,$o)));return $m;}$z=$k?$o:array_merge($a->cs($g,$b,$l),$p,$o);$A=array_merge($a->cr($d,$k,$b,$l),$z);$a->ae($i,fn():int=>$a->g($f,$A));return $m;}protected function eb(int $b,string $c):string{$a=$this;$a->I='';$a->bn($b,$c);$d=$a->I??'';$a->I=NULL;return $d;}protected function bn(int $b,string $e):void{$a=$this;if(!isset($a->f[$b])||$e===''){return;}$g=$a->K;$a->K=$b;$c=$a->f[$b];$f=explode("\n",$c['partial'].$e);$a->f[$b]['partial']=array_pop($f);try{foreach($f as $d){$d=$a->ct($d);$a->f[$b]['lines'][]=$d;if(!$c['is_live']){($c['draw'])($d);}}if($c['is_live']){($c['draw'])();}}finally{$a->K=$g;}}protected function ec(int $b,int $c):array{$a=$this;try{while(ob_get_level()>$c+1){ob_end_flush();}if(ob_get_level()===$c+1){ob_end_flush();}if($a->f[$b]['partial']!==''){$a->bn($b,"\n");}return $a->f[$b]['lines'];}finally{$a->f=array_slice($a->f,0,$b);while(ob_get_level()>$c){ob_end_clean();}}}protected function ct(string $a):string{$a=rtrim($a,"\r");$b=strrpos($a,"\r");return $b===FALSE?$a:substr($a,$b+1);}protected function dz(\Closure $b,int $c):?int{if(!function_exists('pcntl_fork')||!function_exists('posix_kill')||$this->h instanceof \Closure||($this->h===NULL&&ob_get_level()>0)){return NULL;}$d=getmypid();$a=pcntl_fork();if($a!==0){return $a>0?$a:NULL;}$e=$this->h??STDOUT;$f=0;while(posix_getppid()===$d){usleep(static::SPINNER_INTERVAL);fwrite($e,"\033[{$c}A\r".implode(PHP_EOL,$b(++$f)).PHP_EOL);}posix_kill((int)getmypid(),SIGKILL);return NULL;}protected function cp(?int $a):void{if($a===NULL){return;}posix_kill($a,SIGKILL);pcntl_waitpid($a,$b);}protected function aw(string $c):void{$a=$this;$b=match(TRUE){$a->f!==[]=>$a->f[0]['output'],$a->H!==[]=>$a->H[array_key_last($a->H)],default=>$a->h,};if($a->I!==NULL&&$b===NULL){$a->I.=$c;return;}$d=$a->cu();if($d!==NULL&&$b!==NULL){$a->bn($d,$c);return;}if($b instanceof \Closure){$b($c);return;}if($b!==NULL){fwrite($b,$c);return;}echo $c;}protected function ae($a,\Closure $b):void{$this->H[]=$a;try{$b();}finally{array_pop($this->H);}}protected function e(array $c):int{$a=$this;if($a->x!==NULL){return 0;}$a->aw(implode(PHP_EOL,$c).PHP_EOL);$d=$a->am();$b=0;foreach($c as $e){$b+=max(1,(int)ceil($a->n($e)/$d));}$a->Q=NULL;if(!$a->bi()){$a->D+=$b;}return $b;}protected function bi():bool{return $this->cu()!==NULL;}protected function cu():?int{$a=($this->K??count($this->f))-1;return $a>=0?$a:NULL;}protected function g(int $a,array $b):int{$this->au($a);return $this->e($b);}protected function au(int $a):void{if($a<=0){return;}$this->aw("\033[{$a}A\r\033[J");if(!$this->bi()){$this->D-=$a;}}protected function b(string $a,string $b):string{return isset($this->e[$b])?$this->e[$b].$a.$this->e['reset']:$a;}protected function u(string $d,string $e):string{$a=$this;$f=preg_split('/(\033\[[0-9;:]*m)/',$d,-1,PREG_SPLIT_DELIM_CAPTURE)?:[$d];$b='';foreach($f as $g=>$c){if($g%2===0){$b.=$a->c($c);continue;}if(!$a->n){continue;}$b.=$c;if($a->ci($c)){$b.=$a->e[$e]??'';}}return $a->b($b,$e);}protected function ci(string $c):bool{$b=explode(';',substr($c,2,-1));$d=count($b);for($a=0;$a<$d;$a++){if(in_array($b[$a],['38','48','58'],TRUE)){$a+=match($b[$a+1]??''){'5'=>2,'2'=>4,default=>0,};continue;}if(preg_match('/^(?:0*|2[0-9]|39|49)$/',$b[$a])===1){return TRUE;}}return FALSE;}protected function c(string $a):string{$a=preg_replace('/\033\[\??[0-9;]*[A-Za-z]/','',$a)??$a;$a=preg_replace('/[\t\n\x0b\x0c\r]+/',' ',$a)??$a;$a=preg_replace('/[\x00-\x1f\x7f]/','',$a)??$a;return preg_replace('/\p{Cc}/u','',$a)??$a;}protected function cj(string $a):string{return preg_replace('/\033\[\??[0-9;:]*[A-Za-z]/','',$a)??$a;}protected function am():int{$a=$this;if($a->j>0){return $a->j;}return $a->Q??=$a->ck();}protected function ck():int{$a=$this;if($a->N===NULL||microtime(TRUE)-$a->R>=static::COLUMNS_REFRESH){$a->N=$a->ed();$a->R=microtime(TRUE);}return $a->N;}protected function ed():int{$a=trim((string)getenv('COLUMNS'));if(preg_match('/^[1-9]\d*$/',$a)===1){return(int)$a;}if(PHP_OS_FAMILY==='Windows'||!stream_isatty(STDIN)){return static::DEFAULT_COLUMNS;}$b=trim((string)shell_exec('stty size 2>'.static::NULL_DEVICE));return preg_match('/^\d+ ([1-9]\d*)$/',$b,$c)===1?(int)$c[1]:static::DEFAULT_COLUMNS;}protected function n(string $a):int{return array_sum(array_map($this->cv(...),$this->bo($this->cj($a))));}protected function bo(string $d):array{if(preg_match_all('/./su',$d,$e)===FALSE){return str_split($d);}$a=[];foreach($e[0]as $b){$c=array_key_last($a);if($c!==NULL&&$this->cw($a[$c],$b)){$a[$c].=$b;continue;}$a[]=$b;}return $a;}protected function cm(string $a):string{return $this->bo($a)[0]??'';}protected function dx(string $b):string{$a=$this->bo($b);return $a===[]?'':$a[count($a)-1];}protected function cw(string $a,string $b):bool{if(str_ends_with($a,"\u{200D}")||preg_match('/^[\p{Mn}\p{Me}\x{200D}\x{FE00}-\x{FE0F}\x{1F3FB}-\x{1F3FF}]$/u',$b)===1){return TRUE;}return preg_match('/^[\x{1F1E6}-\x{1F1FF}]$/u',$a)===1&&preg_match('/^[\x{1F1E6}-\x{1F1FF}]$/u',$b)===1;}protected function cv(string $a):int{if(preg_match(static::WIDE_PATTERN,$a)===1){return 2;}return preg_match('/^[\p{Mn}\p{Me}\p{Cf}]+$/u',$a)===1?0:1;}protected function f(string $b,string $e,string $c):array{$a=$this;$d=$a->am();return $a->cx($b,$e,$c,$d-$a->n($b),$d-$a->n($c));}protected function cx(string $h,string $e,string $m,int $f,int $i):array{$a=$this;if($f<1||$i<1||$a->n($e)<=$f){return[$h.$e];}$j=$a->ee($e);$n=$a->ef($j,$f,$i);$g=[];$b=$h;$k=0;$d='';$o=0;foreach($j as $p){foreach($p['units']as $c){$l=$n[$o++];if($l===NULL){continue;}while($l>$k){$g[]=$d!==''?$b.$a->e['reset']:$b;$b=$m.$d;$k++;}$b.=$c['text'];if($c['kind']==='style'){$d=$c['text']===$a->e['reset']||preg_match('/^\033\[0*m$/',$c['text'])===1?'':$d.$c['text'];}}}$g[]=$b;return $g;}protected function ee(string $h):array{if(preg_match_all('/\033\[[0-9;:]*m| |./su',$h,$i)===FALSE){preg_match_all('/\033\[[0-9;:]*m| |./s',$h,$i);}$e=[];foreach($i[0]as $a){$f=match(TRUE){preg_match('/^\033\[[0-9;:]*m$/',$a)===1=>'style',$a===' '=>'space',default=>'char',};$g=end($e);if($f==='char'&&$g!==FALSE&&$g['kind']==='char'&&$this->cw($g['text'],$a)){$a=$g['text'].$a;array_pop($e);}$j=$f==='char'?$this->cv($a):(int)($f==='space');$e[]=['text'=>$a,'kind'=>$f,'width'=>$j];}$b=[];foreach($e as $c){$d=array_key_last($b);if($d===NULL||($c['kind']!=='style'&&$b[$d]['space']!==($c['kind']==='space'))){$b[]=['space'=>$c['kind']==='space','width'=>0,'units'=>[]];$d=array_key_last($b);}$b[$d]['units'][]=$c;$b[$d]['width']+=$c['width'];}return $b;}protected function ef(array $i,int $k,int $j):array{$f=[];$c=0;$a=0;$d=$k;$g=FALSE;foreach($i as $l=>$b){if($b['space']&&($c>0||$a>0)){$h=$a+$b['width']+($i[$l+1]['width']??0)<=$d;foreach($b['units']as $e){$f[]=$h||$e['kind']==='style'?$c:NULL;}$a+=$h?$b['width']:0;$g=!$h;continue;}if($g||$a+$b['width']>$d){if($a>0){$c++;$a=0;$d=$j;}$g=FALSE;}foreach($b['units']as $e){if($a>0&&$a+$e['width']>$d){$c++;$a=0;$d=$j;}$f[]=$c;$a+=$e['width'];}}return $f;}protected function eg(string $b,string $c):string{$a=$this;$d=$a->f($b,$c,$b);if(count($d)===1){return $d[0];}$e=$a->b($a->a['ellipsis'],'dim');$f=$a->j;$a->j=max(1,$a->am()-$a->n($e));$g=$a->f($b,$c,$b)[0];$a->j=$f;return $g.$e;}protected function t(string $a):string{return str_repeat(' ',$this->n($a));}protected function d(string $a):string{return $this->c($this->u[$a]??'');}protected function a():string{return $this->b($this->a['bar'],'gray');}protected function q(bool $a):string{return($a?$this->b($this->a['pointer'],'cyan'):' ').' ';}protected function cy(int $c,array $d):string{$b='  ';for($a=1;$a<$c;$a++){$b.=($d[$a]??FALSE)?$this->b($this->a['bar'],'gray').'  ':'   ';}return $b;}protected function h(int $c,array $d):string{$b='  ';for($a=1;$a<=$c;$a++){$b.=($d[$a]??FALSE)?$this->b($this->a['bar'],'gray').'  ':'   ';}return $b;}protected function w(string $a,array $ctx):string{$a=$this->c($a);if(isset($ctx['number'])){$b=$ctx['number'];$c=is_int($ctx['total']??NULL)?sprintf($this->d('step_total'),(int)$b,$ctx['total']):'('.$b.')';return $a.' '.$this->b($c,'dim');}return $a;}protected function ch(string $b):array{$a=$this;$b=$a->c($b);$c=$a->b($a->a['intro'],'gray').$a->b['indent'];return['',...$a->f($c,$a->b($b,'bold'),$a->a().$a->b['indent']),$a->a(),];}protected function bj(string $b):array{$a=$this;$b=$a->c($b);$c=$a->b($a->a['outro'],'gray').$a->b['indent'];return[$a->a(),...$a->f($c,$a->b($b,'green'),$a->t($c)),'',];}protected function eh(string $g,string $e,bool $f):array{$a=$this;$b=$f?['']:[];$h=$a->b($a->a[$f?'intro':'section'],'gray');$c=$a->a().$a->b['indent'];foreach(explode("\n",$g)as $i=>$d){$b=array_merge($b,$a->f(($i===0?$h:$a->a()).$a->b['indent'],$a->b($a->c($d),'bold'),$c));}if($e!==''){foreach(explode("\n",$e)as $d){$b=array_merge($b,$a->f($c,$a->b($a->c($d),'dim'),$c));}}$b[]=$a->a();return $b;}protected function cg(string $h,string $i,int $b=0,array $e=[]):array{$a=$this;$c='note_'.$i;$f=$a->a().($b>0?$a->h($b,$e):$a->b['indent']);$g=explode("\n",$h);$d=$a->bl($a->u(array_shift($g),$c),$a->b($a->a[$c],$c),$b,$e);foreach($g as $j){$d=array_merge($d,$a->f($f,$a->u($j,$c),$f));}$d[]=$a->a().($b>0?$a->h($b,$e):'');return $d;}protected function k(string $a,string $b,string $c,int $d=0,array $e=[]):array{return $this->bl($a,$this->b($this->a[$b],$c),$d,$e);}protected function bl(string $d,string $e,int $b=0,array $c=[]):array{$a=$this;$f=($b>0?$a->a().$a->cy($b,$c):'').$e.$a->b['indent'];return $a->f($f,$d,$a->a().($b>0?$a->h($b,$c):$a->b['indent']));}protected function o(string $f,int $b=0,array $d=[]):array{$a=$this;$e=$a->a().($b>0?$a->h($b,$d):$a->b['indent']);$c=[];foreach(explode("\n",$f)as $g){$c=array_merge($c,$a->f($e,$a->u($g,'dim_italic'),$e));}$c[]=$a->a().($b>0?$a->h($b,$d):'');return $c;}protected function at(string $g,int $c=0,array $h=[]):array{$a=$this;$d=$a->a().($c>0?$a->h($c,$h):'');$e=$d.$a->b['hint_cont'];$f=$d.$a->b['hint_indent'].$a->b($a->a['hint_arrow'],'dim').' ';$b=[];foreach(explode("\n",$g)as $i){$b=array_merge($b,$a->f($f,$a->b($a->c($i),'dim_italic'),$e));$f=$e;}return $b;}protected function m(string $e,int $b=0,array $f=[]):array{$a=$this;if($e===''){return[];}$c=$b>0?$a->h($b,$f):$a->b['indent'];$d=[$a->a().($b>0?$c:'')];foreach(explode("\n",$e)as $g){$d=array_merge($d,$a->f($a->a().$c,$a->b($a->c($g),'dim'),$a->a().$c));}return $d;}protected function p(string $e,int $f=0,array $g=[]):array{$a=$this;if(!$a->G){return[];}$d=$a->d('legend_move')===''?'':$a->a['legend_vertical'].' '.$a->d('legend_move');$h=$a->d('legend_toggle')===''?'':$a->a['legend_horizontal'].' '.$a->d('legend_toggle');$b=$a->d('legend_enter');$c=match($e){'select'=>[$d,$b],'multiselect'=>[$d,$a->d('legend_space'),$b],'suggest'=>[$a->d('legend_type'),$d,$b],'confirm'=>[$h,$a->d('legend_yes_no'),$b],'pause'=>[$a->d('legend_any_key')],'pause_enter'=>[$a->d('legend_continue')],default=>[$b],};if($a->r!==''&&$a->d('legend_back')!==''&&$a->m!==NULL&&$a->cn()!==NULL){$c[]=str_replace('-','+',$a->r).' '.$a->d('legend_back');}$c=array_filter($c,fn(string $i):bool=>trim($i)!=='');return $a->m(implode(' '.$a->a['legend_separator'].' ',$c),$f,$g);}protected function s(?string $b,int $c=0,array $f=[]):array{$a=$this;if($b===NULL){return[];}$d=$a->a().($c>0?$a->h($c,$f):$a->b['indent']);$e=$a->a['error'].' ';return $a->f($d,$a->b($e.$a->c($b),'red'),$d.$a->t($e));}protected function ad(array $b,int $c,int $d,int $e,int $f=0,array $j=[]):array{$a=$this;if($d>=$e){return $b;}$g=$a->a().($f>0?$a->h($f,$j):'');$h=fn(string $k,int $i):string=>$i>0?$g.$a->q(FALSE).$a->b($a->a[$k].' '.$i.' '.$a->d('more'),'dim'):$g;return[$h('scroll_up',$c),...$b,$h('scroll_down',$e-$c-$d),];}protected function bt(string $b,string $c,string $h,string $i):string{$a=$this;$d=$a->b('█','cyan');if($b===''&&$c===''){return $a->b($i,'gray').$d;}$f=$b.$c===$h?'input_default':'white';$b=$a->b($a->c($b),$f);$c=$a->c($c);if($c===''){return $b.$d;}$e=$a->cm($c);$g=$a->b(substr($c,strlen($e)),$f);if($a->e['input_cursor']===''){return $b.$d.$e.$g;}return $b.$a->b($e,'input_cursor').$g;}protected function r(string $c,string $d,int $b=0,array $e=[]):array{$a=$this;$c=$a->c($c);$d=$a->c($d);$f=$a->a().($b>0?$a->h($b,$e):$a->b['indent']);return[...$a->k($c,'completed','cyan',$b,$e),...$a->f($f,$a->b($d,'dim'),$f),$a->a().($b>0?$a->h($b,$e):''),];}protected function ai(string $d,string $c,int $b=0,array $e=[]):array{$a=$this;$d=$a->c($d);$c=$a->c($c);$f=$a->a().($b>0?$a->h($b,$e):$a->b['indent']);$g=$a->b($a->d('cancelled'),'red');return[...$a->k($d,'active','red',$b,$e),...$a->f($f,$c===''?$g:$a->b($c,'dim').' '.$g,$f),$a->a().($b>0?$a->h($b,$e):''),];}protected function cr(string $a,bool $d,int $b=0,array $c=[]):array{return $d?$this->k($a,'completed','cyan',$b,$c):$this->k($a,'active','red',$b,$c);}protected function cq(bool $f,string $g,array $j,string $k,string $h='',int $c=0,array $e=[]):array{$a=$this;$d=$a->a().($c>0?$a->h($c,$e):$a->b['indent']);$b=[];if($g!==''){foreach(explode("\n",$g)as $l){$b=array_merge($b,$a->f($d,$a->u($l,'dim'),$d));}}foreach($j as $m){$b=array_merge($b,$a->bm($m,$c,$e));}$i=$a->a[$f?'success':'error'].' ';$n=$a->b($i.$a->c($k),$f?'green':'red').($h!==''?$a->b($h,'dim'):'');$b=array_merge($b,$a->f($d,$n,$d.$a->t($i)));$b[]=$a->a().($c>0?$a->h($c,$e):'');return $b;}protected function cs(array $b,int $c=0,array $d=[]):array{$a=[];foreach($b as $e){$a=array_merge($a,$this->bm($e,$c,$d));}return $a;}protected function ea(array $b,int $e=0,array $h=[]):array{$a=$this;$c=$b['lines'];if($b['partial']!==''){$c[]=$a->ct($b['partial']);}$d=max(0,count($c)-static::STREAM_WINDOW);$f=$a->a().($e>0?$a->h($e,$h):$a->b['indent']);$g=$d>0?[$f.$a->b($a->a['scroll_up'].' '.$d.' '.$a->d('more'),'dim')]:[];foreach(array_slice($c,$d)as $i){$g[]=$a->eg($f.$a->b['indent'],$a->u($i,'dim'));}return $g;}protected function bm(string $d,int $b=0,array $e=[]):array{$a=$this;$c=$a->a().($b>0?$a->h($b,$e):$a->b['indent']).$a->b['indent'];return $a->f($c,$a->u($d,'dim'),$c);}protected function du(array $k,array $f):array{$a=$this;if($k===[]&&$f===[]){return[];}$g=[];if($f!==[]){$g[]=['cells'=>$a->cz($f,'headers'),'text'=>'','base'=>'bold'];}foreach(array_values($k)as $i=>$j){$g[]=is_string($j)?['cells'=>NULL,'text'=>$j,'base'=>'bold']:['cells'=>$a->cz($j,sprintf('row %d',$i)),'text'=>'','base'=>''];}$c=$a->ei($g);$l=[array_sum($c)+3*(count($c)-1)];$b=[];$d=NULL;foreach($g as $i=>$e){$h=$e['cells']!==NULL;if($d===NULL){$b[]=$a->an($c,FALSE,$h,'top');}elseif(!$h||$d['cells']===NULL||($i===1&&$f!==[])){$b[]=$a->an($c,$d['cells']!==NULL,$h,'middle');}$b=$h?array_merge($b,$a->ax($e['cells'],$c,$e['base'])):array_merge($b,$a->ax([$e['text']],$l,$e['base']));$d=$e;}$b[]=$a->an($c,$d!==NULL&&$d['cells']!==NULL,FALSE,'bottom');$b[]=$a->a();return $b;}protected function cz(mixed $b,string $c):array{if(!is_array($b)){throw new \InvalidArgumentException(sprintf('Table %s of type "%s" is not valid. Provide a list of cells, or a string for a section row.',$c,get_debug_type($b)));}$d=[];foreach($b as $a){if(!is_string($a)&&!is_int($a)&&!is_float($a)){throw new \InvalidArgumentException(sprintf('Table %s holds a cell of type "%s". A cell must be a string, an integer or a float.',$c,get_debug_type($a)));}$d[]=(string)$a;}return $d;}protected function ei(array $f):array{$c=$this;$d=max(1,...array_map(fn(array $b):int=>count($b['cells']??[]),$f));$a=array_fill(0,$d,0);$e=0;foreach($f as $b){if($b['cells']===NULL){$e=max($e,$c->ay($b['text'],$b['base']));continue;}foreach($b['cells']as $g=>$h){$a[$g]=max($a[$g],$c->ay($h,$b['base']));}}$a[$d-1]+=max(0,$e-(array_sum($a)+3*($d-1)));$i=$c->am()-$c->n($c->az())-3*$d-1;while(array_sum($a)>$i&&max($a)>1){$j=array_keys($a,max($a),TRUE);$a[(int)end($j)]--;}return array_values($a);}protected function dv(string $e,string $c):array{$a=$this;$f=$a->am()-$a->n($a->az())-4;$b=max(1,min($f,max($a->ay($e,''),$a->ay($c,'bold'))));$d=[$a->an([$b],FALSE,FALSE,'top')];if($c!==''){$g=$a->da($c,'bold',$b);$h=max(0,...array_map($a->n(...),$g));$d=array_merge($d,$a->ax([$c],[$b],'bold'));$d[]=$a->db([$a->b(str_repeat($a->a['box_horizontal'],$h),'gray')],[$b]);}return[...$d,...$a->ax([$e],[$b],''),$a->an([$b],FALSE,FALSE,'bottom'),$a->a(),];}protected function az():string{return $this->a().$this->b['indent'];}protected function an(array $d,bool $b,bool $c,string $e):string{$a=$this;$f=match(TRUE){$b&&$c=>'box_cross',$b=>'box_bottom_tee',$c=>'box_top_tee',default=>'box_horizontal',};[$g,$h]=match($e){'top'=>['box_top_left','box_top_right'],'bottom'=>['box_bottom_left','box_bottom_right'],default=>['box_left_tee','box_right_tee'],};$i=array_map(fn(int $j):string=>str_repeat($a->a['box_horizontal'],$j+2),$d);return $a->az().$a->b($a->a[$g].implode($a->a[$f],$i).$a->a[$h],'gray');}protected function ax(array $e,array $c,string $f):array{$a=[];foreach($c as $g=>$h){$a[]=$this->da($e[$g]??'',$f,$h);}$i=max(1,...array_map(count(...),$a));$d=[];for($b=0;$b<$i;$b++){$d[]=$this->db(array_map(fn(array $j):string=>$j[$b]??'',$a),$c);}return $d;}protected function db(array $e,array $f):string{$a=$this;$b=$a->b($a->a['box_vertical'],'gray');$c=$a->az().$b;foreach($f as $g=>$h){$d=$e[$g]??'';$c.=' '.$d.str_repeat(' ',max(0,$h-$a->n($d))).' '.$b;}return $c;}protected function da(string $g,string $h,int $b):array{$d=[];foreach(explode("\n",$g)as $e){$a=str_repeat(' ',strspn($e,' '));$a=strlen($a)<$b?$a:'';foreach($this->cx('',$this->u($e,$h),$a,$b,$b-strlen($a))as $c){preg_match_all('/\033\[[0-9;:]*m/',$c,$i);$f=end($i[0]);$d[]=$f!==FALSE&&preg_match('/^\033\[0*m$/',$f)!==1?$c.$this->e['reset']:$c;}}return $d;}protected function ay(string $a,string $b):int{return max(array_map(fn(string $c):int=>$this->n($this->u($c,$b)),explode("\n",$a)));}protected static function dm(array $h,array $f):?array{['intro'=>$b,'outro'=>$c,'cancelled'=>$d,'numbering'=>$e,'symbols_unicode'=>$i,'symbols_ascii'=>$j,'colors'=>$k,'spacing'=>$l,'labels'=>$m,'unicode'=>$n,'ansi'=>$o,'env_prefix'=>$p,'truthy'=>$q,'falsy'=>$r,'output'=>$s,'interactive'=>$t,'scroll'=>$u,'placeholder_fallback'=>$v,'width'=>$w,'legend'=>$x,'back_key'=>$y,]=$f;$a=static::j();$g=NULL;if(is_string($e)&&$e!=='total'){throw new \InvalidArgumentException(sprintf('Numbering "%s" for "numbering" is not valid. Use TRUE to number steps, "total" to number them with the total, or FALSE for no numbers.',$e));}if($i!==NULL||$j!==NULL||$k!==NULL||$l!==NULL||$m!==NULL||$n!==NULL||$o!==NULL||$p!==NULL||$q!==NULL||$r!==NULL||$s!==NULL||$t!==NULL||$u!==NULL||$v!==NULL||$w!==NULL||$x!==NULL||$y!==NULL){$g=$a->dh();static::configure($i,$j,$k,$l,$m,$n,$o,$p,$q,$r,$s,$t,$u,$v,$w,$x,$y);}$a->c=[];$a->co();static::$k=TRUE;try{$a->dc($h);$f=['numbering'=>$e,];if($a->d){$a->v();}if($a->l!==NULL){register_shutdown_function(function()use($a):void{$a->i();});}if($b!==NULL){is_callable($b)?$b($a->c):$a->e($a->ch($b));}do{$a->c=[];$a->o=[];$a->w=$b!==NULL;$z=$a->dd($h,0,$f,'');}while($z==='back');if($z==='cancelled'){if($d!==NULL){is_callable($d)?$d($a->c):$a->e($a->bj($d));}return NULL;}if($c!==NULL){is_callable($c)?$c($a->c):$a->e($a->bj($c));}return $a->c;}finally{$a->i();$a->m=NULL;$a->w=FALSE;$a->co();static::$k=FALSE;if($g!==NULL){$a->di($g);}}}protected function dc(array $e):void{$b=$this;foreach($e as $a){if($b->ao($a)){continue;}if($b->ba($a)){$f=$a['__type'];$b->cf($f);continue;}$c=$a['__key'];$d=$b->z.strtoupper($c);if(preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/',$d)!==1){throw new \InvalidArgumentException(sprintf('Step "%s" is looked up as the environment variable "%s", which cannot be exported. A variable name may hold only letters, digits and underscores, and may not start with a digit.',$c,$d));}$g=$a['__children'];$b->dc($g);}}protected function ap(array $b):?callable{$a=$b['__condition']??NULL;return is_callable($a)?$a:NULL;}protected function ao(array $a):bool{return array_key_exists('__section',$a);}protected function ba(array $a):bool{return array_key_exists('__note',$a);}protected function bb(array $b,array $c):bool{foreach($b as $d){$a=$this->ap($d);if($a===NULL||$a($c)){return TRUE;}}return FALSE;}protected function ej(array $e):int{$a=$this;$c=0;foreach($e as $b){if($a->ao($b)||$a->ba($b)){continue;}$d=$a->ap($b);if($d===NULL||$a->ek($d)){$c++;}}return $c;}protected function ek(callable $a):bool{set_error_handler(static function(int $b,string $c):never{throw new \ErrorException($c,0,$b);});try{return(bool)$a($this->c);}catch(\Throwable){return FALSE;}finally{restore_error_handler();}}protected function bp(int $a,bool $b):void{if($a<1){return;}if($b){unset($this->o[$a]);return;}$this->o[$a]=TRUE;}protected function l(mixed $d,array $e):array{$a=$this;$b=$a->m;if($b===NULL){return $e;}$c=$a->c;$c[(string)$b['key']]=$d;$a->bp($b['depth'],!$a->bb($b['rest'],$c));return $a->o;}protected function el(array $b,array $f):void{$a=$this;$c=$a->ap($b);if($c!==NULL&&!$c($a->c)){return;}$d=[];foreach($f as $e){if($a->ao($e)){break;}$d[]=$e;}if(!$a->bb($d,$a->c)){return;}$g=$b['__section'];$h=$b['__description']??'';$a->e($a->eh($g,$h,!$a->w));$a->w=TRUE;}protected function em(array $b,array $e,int $c):void{$a=$this;$d=$a->ap($b);if($d!==NULL&&!$d($a->c)){return;}$a->bp($c,!$a->bb($e,$a->c));$f=$b['__note'];$g=$b['__type'];$a->e($a->cg($f,$g,$c,$a->o));$a->w=TRUE;}protected function bq(array $c):void{$b=$this;foreach($c as $a){if($b->ao($a)||$b->ba($a)){continue;}$d=$a['__key'];$b->c[$d]=NULL;$e=$a['__children'];$b->bq($e);}}protected function dd(array $e,int $d,array $i,string $l):string{$a=$this;$f=0;$g=0;foreach($e as $b){$g++;if($a->ao($b)){$a->el($b,array_slice($e,$g));continue;}if($a->ba($b)){$a->em($b,array_slice($e,$g),$d);continue;}$c=$b['__key'];$s=$b['__call'];$j=$b['__children'];$m=$a->ap($b);if($m!==NULL&&!$m($a->c)){$a->bq([$b]);continue;}$f++;$h=array_slice($e,$g);$a->bp($d,$h===[]);$a->m=['key'=>$c,'depth'=>$d,'rest'=>$h];$n=$l!==''?$l.'.'.$f:(string)$f;$t=$d===0&&($i['numbering']??FALSE)==='total'?$f+$a->ej($h):NULL;$o=getenv($a->z.strtoupper($c));$ctx=['depth'=>$d,'is_last'=>$h===[],'open'=>$a->o,'results'=>$a->c,'number'=>($i['numbering']??FALSE)?$n:NULL,'total'=>$t,'discovered'=>$o!==FALSE?$o:NULL,'truthy'=>$a->A,'falsy'=>$a->B,];if(array_key_exists($c,$a->J)){$ctx['previous']=$a->J[$c];}$p=$a->x!==NULL&&$a->x!==$c&&array_key_exists($c,$a->E);if(!$p){$a->x=NULL;$a->p[]=['key'=>$c,'depth'=>$d,'rows'=>$a->D,'asked'=>FALSE];}$k=$p?$a->E[$c]:$s($ctx);if($k===NULL){$a->m=NULL;if($a->y!==NULL){$a->dy($a->y);return'back';}return'cancelled';}$a->c[$c]=$k;$a->w=TRUE;$a->l($k,$a->o);$a->m=NULL;if(!$a->bb($j,$a->c)){$a->bq($j);continue;}$q=$d+1;$u=$a->a().$a->cy($q,$a->o).$a->a();$a->e([$u]);$r=$a->dd($j,$q,$i,$n);if($r!=='done'){return $r;}}return'done';}}interface PromptyStepInterface{public function key():string;public function widget():string;public function arguments(array $results):array;public function condition(array $results):bool;}class PromptyFlow{protected array $g=[[]];protected array $i=[];protected array $T=[];protected?string $O=NULL;protected $U;protected \Closure $V;public function __construct(\Closure $runner){$this->V=$runner;}public function intro(string|callable $message):static{$this->U=$message;return $this;}public function section(string $title,string $description=''):static{$b=$title;$a=$this;if($a->i!==[]){throw new \LogicException(sprintf('Section "%s" is among the children of step "%s". A section heads top-level steps, so close the children with end() first.',$b,$a->i[count($a->i)-1]));}if(trim($b)===''){throw new \InvalidArgumentException('A section must have a title: it is the heading drawn above its steps.');}return $a->br(['__section'=>$b,'__description'=>$description,'__condition'=>NULL]);}public function note(string $message,string $type='info'):static{return $this->br(['__note'=>$message,'__type'=>$type,'__condition'=>NULL]);}public function text(string $key,string|\Closure $label,string|\Closure $default='',string|\Closure $placeholder='',string|\Closure $description='',string|\Closure $help='',mixed $discovered=NULL,?callable $transform=NULL,bool|string|\Closure $required=FALSE,?callable $validate=NULL,):static{$a=['label'=>$label,'default'=>$default,'placeholder'=>$placeholder,'description'=>$description,'help'=>$help,'discovered'=>$discovered,'required'=>$required,];$b=['transform'=>$transform,'validate'=>$validate];return $this->x($key,Prompty::text(...),$a,$b);}public function select(string $key,string|\Closure $label,array|\Closure $options=[],string|\Closure $default='',string|\Closure $description='',array|\Closure $hints=[],int|\Closure|null $scroll=NULL,string|\Closure $help='',mixed $discovered=NULL,?callable $transform=NULL,bool|string|\Closure $required=FALSE,?callable $validate=NULL,):static{$a=['label'=>$label,'options'=>$options,'default'=>$default,'description'=>$description,'hints'=>$hints,'scroll'=>$scroll,'help'=>$help,'discovered'=>$discovered,'required'=>$required,];$b=['transform'=>$transform,'validate'=>$validate];return $this->x($key,Prompty::select(...),$a,$b);}public function multiselect(string $key,string|\Closure $label,array|\Closure $options=[],array|\Closure $default=[],string|\Closure $description='',array|\Closure $hints=[],int|\Closure|null $scroll=NULL,string|\Closure $order='options',string|\Closure $help='',mixed $discovered=NULL,?callable $transform=NULL,bool|string|\Closure $required=FALSE,?callable $validate=NULL,):static{$a=['label'=>$label,'options'=>$options,'default'=>$default,'description'=>$description,'hints'=>$hints,'scroll'=>$scroll,'help'=>$help,'discovered'=>$discovered,'required'=>$required,];$b=['order'=>$order,'transform'=>$transform,'validate'=>$validate];return $this->x($key,Prompty::multiselect(...),$a,$b);}public function suggest(string $key,string|\Closure $label,array|\Closure $options=[],string|\Closure $default='',string|\Closure $placeholder='',string|\Closure $description='',int|\Closure|null $scroll=NULL,string|\Closure $help='',mixed $discovered=NULL,?callable $transform=NULL,bool|string|\Closure $required=FALSE,?callable $validate=NULL,):static{$a=['label'=>$label,'default'=>$default,'placeholder'=>$placeholder,'description'=>$description,'scroll'=>$scroll,'help'=>$help,'discovered'=>$discovered,'required'=>$required,];$b=['options'=>$options,'transform'=>$transform,'validate'=>$validate];return $this->x($key,Prompty::suggest(...),$a,$b);}public function confirm(string $key,string|\Closure $label,bool|\Closure $default=TRUE,string|\Closure $description='',string|\Closure $help='',mixed $discovered=NULL,?callable $transform=NULL,bool|string|\Closure $required=FALSE,?callable $validate=NULL,):static{$a=['label'=>$label,'default'=>$default,'description'=>$description,'help'=>$help,'discovered'=>$discovered,'required'=>$required,];$b=['transform'=>$transform,'validate'=>$validate];return $this->x($key,Prompty::confirm(...),$a,$b);}public function pause(string $key,string|\Closure $label,string|\Closure $description='',string|\Closure $help='',bool|\Closure $enter_only=FALSE,bool|\Closure $erase=FALSE,):static{$a=['label'=>$label,'description'=>$description,'help'=>$help,'enter_only'=>$enter_only,'erase'=>$erase,];return $this->x($key,Prompty::pause(...),$a);}public function task(string $key,string|\Closure $label,callable $action,string|callable $hint='',string|callable $success='',string|callable $failure='',bool|\Closure $stream=FALSE,):static{$a=['label'=>$label,'stream'=>$stream];$b=['action'=>$action,'hint'=>$hint,'success'=>$success,'failure'=>$failure];return $this->x($key,Prompty::task(...),$a,$b,static fn(mixed $c):bool=>$c!==FALSE);}public function add(PromptyStepInterface $step):static{$b=$step;$a=$b->key();$f=static function(array $ctx)use($b,$a):mixed{$c=['text'=>Prompty::text(...),'select'=>Prompty::select(...),'multiselect'=>Prompty::multiselect(...),'suggest'=>Prompty::suggest(...),'confirm'=>Prompty::confirm(...),];$d=$b->widget();if(!isset($c[$d])){throw new \UnexpectedValueException(sprintf('Step "%s" names the widget "%s". A step provider names 1 of: %s.',$a,$d,implode(', ',array_keys($c))));}$g=$ctx['results'];$e=$b->arguments($g);if(array_key_exists('ctx',$e)){throw new \UnexpectedValueException(sprintf('The arguments of step "%s" include "ctx". The flow passes the context to the widget itself.',$a));}if(!is_string($e['label']??NULL)){throw new \UnexpectedValueException(sprintf('The arguments of step "%s" have no string "label". A step provider returns named arguments, such as [\'label\' => \'Dessert\'].',$a));}return static::de($c[$d],$e,$ctx);};return $this->df($a,$f,$b->condition(...));}public function when(callable $condition):static{$a=$this;$b=count($a->g)-1;$c=array_key_last($a->g[$b]);if($c===NULL){throw new \LogicException($a->dg('when() sets the condition of the step added before it'));}$a->g[$b][$c]['__condition']=$condition;return $a;}public function children():static{$a=$this;$c=count($a->g)-1;$d=array_key_last($a->g[$c]);if($d===NULL){throw new \LogicException($a->dg('children() opens a scope under the step added before it'));}$b=$a->g[$c][$d];if(!isset($b['__key'])||!is_string($b['__key'])){$e=isset($b['__section'])?'section':'note';$f=$b['__section']??$b['__note'];throw new \LogicException(sprintf('children() follows the %s "%s". Only a step takes children.',$e,$f));}$g=$b['__children'];$a->i[]=$b['__key'];$a->g[]=$g;return $a;}public function end():static{$a=$this;if($a->i===[]){throw new \LogicException($a->O===NULL?'end() closes a scope opened with children(), and none is open.':sprintf('end() after step "%s" closes a scope opened with children(), and none is open.',$a->O));}$d=array_pop($a->g);array_pop($a->i);$b=count($a->g)-1;$c=array_key_last($a->g[$b]);if($c!==NULL){$a->g[$b][$c]['__children']=$d;}return $a;}public function run(string|callable|null $intro=NULL,string|callable|null $outro=NULL,string|callable|null $cancelled=NULL,bool|string $numbering=FALSE,?array $symbols_unicode=NULL,?array $symbols_ascii=NULL,?array $colors=NULL,?array $spacing=NULL,?array $labels=NULL,?bool $unicode=NULL,?bool $ansi=NULL,?string $env_prefix=NULL,?array $truthy=NULL,?array $falsy=NULL,mixed $output=NULL,?bool $interactive=NULL,?int $scroll=NULL,?bool $placeholder_fallback=NULL,?int $width=NULL,?bool $legend=NULL,?string $back_key=NULL,):?array{$a=$this;if($a->i!==[]){throw new \LogicException(sprintf('The children of step "%s" are still open. Close them with end() before run().',$a->i[count($a->i)-1]));}$b=['intro'=>$intro??$a->U,'outro'=>$outro,'cancelled'=>$cancelled,'numbering'=>$numbering,'symbols_unicode'=>$symbols_unicode,'symbols_ascii'=>$symbols_ascii,'colors'=>$colors,'spacing'=>$spacing,'labels'=>$labels,'unicode'=>$unicode,'ansi'=>$ansi,'env_prefix'=>$env_prefix,'truthy'=>$truthy,'falsy'=>$falsy,'output'=>$output,'interactive'=>$interactive,'scroll'=>$scroll,'placeholder_fallback'=>$placeholder_fallback,'width'=>$width,'legend'=>$legend,'back_key'=>$back_key,];return($a->V)($a->g[0],$b);}protected function x(string $f,\Closure $c,array $d,array $e=[],?\Closure $b=NULL):static{$g=static function(array $ctx)use($c,$d,$e,$b):mixed{$h=$ctx['results'];$i=array_map(static fn(mixed $a):mixed=>$a instanceof \Closure?$a($h):$a,$d);$a=static::de($c,[...$i,...$e],$ctx);return $b instanceof \Closure?$b($a):$a;};return $this->df($f,$g);}protected static function de(\Closure $a,array $b,array $ctx):mixed{return $a(...$b,ctx:$ctx);}protected function df(string $a,\Closure $c,?callable $d=NULL):static{$b=$this;if($a===''){throw new \InvalidArgumentException('A step must have a key: it names the answer in the results and the variable the answer can come from.');}if(isset($b->T[$a])){throw new \InvalidArgumentException(sprintf('Step "%s" appears more than once in the flow. Each answer is stored under its step key, so a later step would overwrite the earlier answer in the results.',$a));}$b->T[$a]=TRUE;$b->O=$a;return $b->br(['__key'=>$a,'__call'=>$c,'__children'=>[],'__condition'=>$d]);}protected function br(array $a):static{$this->g[count($this->g)-1][]=$a;return $this;}protected function dg(string $a):string{if($this->i===[]){return $a.', and the flow has no step yet.';}return sprintf('%s, and the children of step "%s" have no step yet.',$a,$this->i[count($this->i)-1]);}}
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

  if (in_array('--version', $argv, TRUE)) {
    print_version();

    return;
  }

  $dir = (string) getcwd();

  if (is_empty_directory($dir, __FILE__)) {
    install_from_archive($dir, __FILE__, array_slice($argv, 1));

    return;
  }

  // The prompts rewrite the template in place, so a directory without it gets
  // nothing but the placeholder logo.
  if (!is_file('your_extension.info.yml')) {
    throw new \Exception('The current directory holds no copy of the template. Run the installer in a copy of the template, or in an empty directory to download the template there.');
  }

  $branches = parse_branch_list((string) getenv('INSTALL_BRANCHES'));
  $repository = parse_github_repository((string) getenv('INSTALL_GITHUB_REPOSITORY'));

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

  $results = Prompty::flow()
    ->text('name', 'Extension name', placeholder: 'My Extension')
    ->text('machine_name', 'Machine name', placeholder: 'my_extension')
    ->select('type', 'Extension type', options: [
      'module' => 'Module',
      'theme' => 'Theme',
    ])
    ->multiselect(
      'drupal_version',
      'Target Drupal versions',
      options: drupal_version_options(),
      default: drupal_version_default(),
      description: 'CI runs against every selected major. Check any other major you also support.',
    )
    ->multiselect('command_wrapper', 'Command wrapper', options: [
      'ahoy' => 'Ahoy',
      'makefile' => 'Makefile',
    ])
    ->multiselect(
      'tools',
      'Tools',
      options: $tool_options,
      default: array_keys($tool_options),
      description: 'All tools are included by default. Uncheck any to remove from your project.',
    )
    ->confirm(
      'examples',
      'Keep example lifecycle scripts',
      default: FALSE,
      description: 'Sample hooks that only print a marker line when each build phase runs. Keep them as a starting point for your own scripts.',
    )
    ->confirm('remove_self', 'Remove this script')
    ->confirm('proceed', 'Proceed with installation')
    ->run(
      intro: 'Eddy - Drupal extension scaffold',
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

  process($name, $machine_name, $type, $drupal_versions, $command_wrapper, $tools_remove, $remove_examples, $remove_self, $branches, template_version(TEMPLATE_VERSION), $repository);

  // The flow shows each answer as given, so a derived machine name appears
  // only here.
  print implode(PHP_EOL, [
    'Name: ' . $name,
    'Machine name: ' . $machine_name,
    'Type: ' . $type,
    'Drupal: ' . implode(', ', $drupal_versions),
    'Wrapper: ' . implode(', ', $command_wrapper ?: ['None']),
    'Removed tools: ' . implode(', ', $tools_remove ?: ['None']),
  ]) . PHP_EOL;
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
 * highest of these majors is the newest one with a stable release, which the
 * shipped 'composer.dev.json' sets as the build default.
 *
 * @return non-empty-array<int, string>
 *   The majors to start checked.
 */
function drupal_version_default(): array {
  return ['11'];
}

/**
 * Pick the Drupal major that a local build uses by default.
 *
 * A major without a stable release can require a newer PHP than the other
 * majors, so the default is the highest selected major with a stable release.
 * When every selected major is newer, it is the lowest selected major.
 *
 * @param non-empty-array<int|string> $majors
 *   The selected Drupal majors, in any order.
 *
 * @return string
 *   The major, such as '11'.
 */
function drupal_version_build_default(array $majors): string {
  $newest_stable = max(array_map(intval(...), drupal_version_default()));
  $majors = array_map(intval(...), $majors);
  $stable = array_filter($majors, static fn(int $major): bool => $major <= $newest_stable);

  return (string) ($stable === [] ? min($majors) : max($stable));
}

/**
 * Define the removal footprint of each Drupal major beyond its blocks.
 *
 * A file with a 'DRUPAL_<major>' block holds one for every major, so a file
 * or text that only 1 major has is listed here instead. 'files' lists the
 * files that go with the major, and 'replacements' maps the template text
 * that names them to the text without them.
 *
 * '.eddy/CLAUDE.md' lists the steps for adding a Drupal major.
 *
 * @return array<int, array{
 *   files: list<string>,
 *   replacements: array<string, string>,
 *   }>
 *   Map of Drupal major to its removal specification. PHP casts the
 *   numeric-string keys to integers.
 */
function drupal_version_specs(): array {
  return [
    '10' => [
      'files' => ['phpunit.d10.xml'],
      'replacements' => [
        '# phpunit.d10.xml    export-ignore' . "\n" => '',
        'run the tests, and `phpunit.d10.xml` replaces it in a Drupal 10 build. Both use' => 'run the tests. It uses',
      ],
    ],
  ];
}

/**
 * Define the PHP versions that each Drupal major supports.
 *
 * The 'test' matrix of '.github/workflows/test.yml' runs each major on the
 * lowest and the highest of its versions.
 *
 * @return non-empty-array<int, non-empty-list<string>>
 *   Map of Drupal major to its PHP versions, in ascending order. PHP casts the
 *   numeric-string keys to integers.
 */
function drupal_version_php_versions(): array {
  return [
    '10' => ['8.3', '8.4'],
    '11' => ['8.3', '8.4', '8.5'],
    '12' => ['8.5'],
  ];
}

/**
 * Define the removal footprint of each extension type beyond its blocks.
 *
 * An 'EXTENSION_<TYPE>' block holds the lines that only that type has. 'files'
 * lists the template files that only that type uses, and 'cspell_words' the
 * words they bring into the CSpell 'words' of '.cspell.json'.
 *
 * The example extension is a module, so the files of its service, its settings
 * form and their tests are the module's. A theme's own file is its '.theme'
 * file.
 *
 * '.eddy/CLAUDE.md' describes what a theme keeps.
 *
 * @return array<string, array{
 *   files: list<string>,
 *   cspell_words: list<string>,
 *   }>
 *   Map of extension type to its removal specification.
 */
function extension_type_specs(): array {
  return [
    'module' => [
      'files' => [
        'your_extension.install',
        'your_extension.links.menu.yml',
        'your_extension.module',
        'your_extension.routing.yml',
        'your_extension.services.yml',
        'config/schema/your_extension.schema.yml',
        'src/Form/YourExtensionForm.php',
        'src/YourExtensionService.php',
        'tests/src/Functional/YourExtensionFunctionalTest.php',
        'tests/src/Kernel/YourExtensionFormKernelTest.php',
        'tests/src/Kernel/YourExtensionHooksKernelTest.php',
        'tests/src/Kernel/YourExtensionServiceKernelTest.php',
        'tests/src/Unit/YourExtensionServiceUnitTest.php',
      ],
      'cspell_words' => ['noscript'],
    ],
    'theme' => [
      'files' => ['your_extension.theme', 'tests/src/Unit/YourExtensionPreprocessUnitTest.php'],
      'cspell_words' => [],
    ],
  ];
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

Run it in a copy of the template. In an empty directory, it downloads its own
version of the template first.

Options:
  --help                This help.
  --version             The Eddy version that the installer installs.

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
  INSTALL_GITHUB_REPOSITORY
                          GitHub repository the README badges name, as
                          owner/name (e.g. drevops/eddy). Defaults to the
                          GitHub origin remote of the clone this directory is
                          the root of, or to the machine name.
  INSTALL_TEMPLATE_ARCHIVE
                          The .tar.gz archive to download in an empty
                          directory, as a URL or a path, with the template in
                          1 top-level directory, as GitHub builds it. Defaults
                          to the release archive of the installer's version,
                          or to the GitHub archive of a version without one.
  INSTALL_TEMPLATE_ARCHIVE_SHA256
                          The SHA-256 checksum that the archive in
                          INSTALL_TEMPLATE_ARCHIVE must match. Without it, the
                          installer warns that the archive is not verified.

EOF;
  print $out;
}

/**
 * Print the version of the template that the installer installs.
 *
 * A copy without a version, such as a clone's, prints 'development'.
 */
function print_version(): void {
  print (template_version(TEMPLATE_VERSION) ?? 'development') . PHP_EOL;
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
 * @param string|null $repository
 *   The GitHub repository the README badges name, as 'owner/name', or NULL to
 *   read it from the 'origin' remote.
 */
function process(string $extension_name, string $extension_machine_name, string $extension_type, array $drupal_versions, array $command_wrapper, array $tools_remove, bool $remove_examples, bool $remove_self, array $branches = [], ?string $version = NULL, ?string $repository = NULL): void {
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

  if (!array_key_exists($extension_type, extension_type_specs())) {
    throw new \Exception(sprintf('Unsupported extension type: %s.', $extension_type));
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

  process_drupal_version_text($selected_majors);

  // 'process_drupal_version_text()' finds the majors with a PHPUnit
  // configuration of their own by their files, so the files go after it.
  remove_drupal_versions(array_diff($supported_majors, $selected_majors));

  set_composer_dev_eddy_value('drupal-version', drupal_version_build_default($selected_majors));

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

  // 'extension_type_specs()' names the template files, so they go before
  // 'process_internal()' renames them.
  $types_remove = array_values(array_diff(array_keys(extension_type_specs()), [$extension_type]));
  remove_extension_types($types_remove);

  process_extension_type_text($extension_type);

  process_internal($extension_name, $extension_machine_name, $extension_type, $repository ?? origin_github_repository());

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

  // This runs after every other change, so the words in use come from the
  // files the project ships.
  remove_unused_cspell_words([...tool_cspell_words($tools_remove), ...extension_type_cspell_words($types_remove)]);
}

/**
 * Render the text that follows from the targeted Drupal majors.
 *
 * The template names every supported major in the 'core_version_requirement'
 * of the extension and in the comment that opens 'phpunit.xml', and its
 * README shows a badge for every PHP version they support. Each is replaced
 * with the same text for the selected majors.
 *
 * @param array<string> $majors
 *   The selected Drupal majors.
 */
function process_drupal_version_text(array $majors): void {
  $supported = array_map(strval(...), array_keys(drupal_version_options()));

  replace_string_content(core_version_requirement($supported), core_version_requirement($majors));
  replace_string_content(php_badges($supported), php_badges($majors));

  // A 'phpunit.d<major>.xml' replaces 'phpunit.xml' in the build of its major.
  $own_config_majors = array_filter($supported, static fn(string $major): bool => file_exists('phpunit.d' . $major . '.xml'));
  replace_string_content(phpunit_config_comment(array_diff($supported, $own_config_majors)), phpunit_config_comment(array_diff($majors, $own_config_majors)));
}

/**
 * Remove deselected Drupal majors from the project.
 *
 * Removing a major strips its '#;< DRUPAL_<major> ... #;> DRUPAL_<major>'
 * blocks from every project file. Markers for kept majors are stripped later
 * by 'remove_special_comments()'.
 *
 * It also removes the major's files listed in 'drupal_version_specs()' and
 * rewrites the text in its 'replacements'.
 *
 * @param array<string> $majors
 *   The Drupal majors to remove.
 */
function remove_drupal_versions(array $majors): void {
  $specs = drupal_version_specs();

  foreach ($majors as $major) {
    remove_tokens_with_content('DRUPAL_' . $major);

    if (!isset($specs[$major])) {
      continue;
    }

    foreach ($specs[$major]['files'] as $file) {
      @unlink($file);
    }

    foreach ($specs[$major]['replacements'] as $search => $replacement) {
      replace_string_content($search, $replacement);
    }
  }
}

/**
 * Remove the unselected extension types from the project.
 *
 * Removing a type strips its '#;< EXTENSION_<TYPE> ... #;> EXTENSION_<TYPE>'
 * blocks from every project file. Markers for the kept type are stripped later
 * by 'remove_special_comments()'.
 *
 * It also removes the type's files listed in 'extension_type_specs()', with
 * each directory that the removal leaves empty.
 *
 * @param array<string> $types
 *   The extension types to remove.
 */
function remove_extension_types(array $types): void {
  $specs = extension_type_specs();

  foreach ($types as $type) {
    remove_tokens_with_content('EXTENSION_' . strtoupper($type));

    foreach ($specs[$type]['files'] ?? [] as $file) {
      remove_file($file);
    }
  }
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

  foreach (['', 'build', 'info', 'lint', 'test'] as $command) {
    replace_string_content(command_wrapper_text($wrappers, $command), command_wrapper_text($command_wrapper, $command));
  }
}

/**
 * Render the text that follows the extension type.
 *
 * The template names the directories of both extension types, in the docs and
 * on the lines of 'package.json' and 'jest.config.js' that list both, where no
 * marker block fits. Each mention is replaced with the directory of the
 * extension's type.
 *
 * The FunctionalJavascript base class enables the extension as a module, with
 * Stark as the default theme. A theme cannot be enabled as a module, so its
 * base class installs it as the default theme instead.
 *
 * @param string $extension_type
 *   The extension type: 'module' or 'theme'.
 */
function process_extension_type_text(string $extension_type): void {
  $dir = $extension_type === 'theme' ? 'themes' : 'modules';

  replace_string_content(' (`web/themes/custom/` for a theme)', '');
  replace_string_content('`build/web/modules/custom/` (module) or `build/web/themes/custom/` (theme)', '`build/web/' . $dir . '/custom/`');
  replace_string_content('web/modules/custom/your_extension/tests/', 'web/' . $dir . '/custom/your_extension/tests/');
  replace_string_content('web/modules/custom web/themes/custom', 'web/' . $dir . '/custom');
  replace_string_content('\"web/modules/custom/**/*.css\" \"web/themes/custom/**/*.css\"', '\"web/' . $dir . '/custom/**/*.css\"');
  replace_string_content("['web/modules/custom', 'web/themes/custom']", "['web/" . $dir . "/custom']");

  if ($extension_type === 'theme') {
    $module_setup = implode("\n", [
      "  protected \$defaultTheme = 'stark';",
      '',
      '  /**',
      '   * {@inheritdoc}',
      '   */',
      "  protected static \$modules = ['your_extension'];",
    ]);

    replace_string_content($module_setup, "  protected \$defaultTheme = 'your_extension';");
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
 * Render the README badges of the PHP versions that Drupal majors support.
 *
 * @param array<int|string> $majors
 *   The Drupal majors, in any order.
 *
 * @return string
 *   The badge lines, 1 per PHP version in ascending order, or an empty string
 *   for no majors.
 */
function php_badges(array $majors): string {
  $php_versions = drupal_version_php_versions();
  $versions = [];

  foreach ($majors as $major) {
    $versions = array_merge($versions, $php_versions[(int) $major] ?? []);
  }

  $badges = array_map(static fn(string $version): string => '![PHP ' . $version . '](https://img.shields.io/badge/PHP-' . $version . '-777BB4.svg)', sort_php_versions($versions));

  return implode("\n", $badges);
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
 * Sort PHP versions in ascending order and drop duplicates.
 *
 * @param array<string> $versions
 *   The PHP versions, such as '8.3'.
 *
 * @return list<string>
 *   The sorted versions.
 */
function sort_php_versions(array $versions): array {
  $versions = array_unique($versions);
  sort($versions, SORT_NATURAL);

  return $versions;
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
 * @param string|null $repository
 *   The GitHub repository the README badges name, as 'owner/name', or NULL
 *   to name the machine name for both parts.
 */
function process_internal(string $extension_name, string $extension_machine_name, string $extension_type, ?string $repository = NULL): void {
  $extension_machine_name_class = convert_string($extension_machine_name, 'class_name');

  // Protect the attribution link from bulk replacements.
  $attribution_link = '[Eddy](https://github.com/drevops/eddy)';
  $attribution_link_token = '__ATTRIBUTION_LINK__';
  replace_string_content($attribution_link, $attribution_link_token);

  // A repository can hold a placeholder, such as the owner 'alexskrypnyk',
  // so it goes in after the bulk replacements.
  $repository_token = '__GITHUB_REPOSITORY__';

  if ($repository !== NULL) {
    replace_string_content('YourNamespace/your_extension', $repository_token);
  }

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
  replace_string_content('Drupal module scaffold FE example used for template testing.', 'Provides ' . $extension_machine_name . ' functionality.');
  replace_string_content('Drupal extension scaffold', $extension_name);
  replace_string_content('type: module', 'type: ' . $extension_type);
  replace_string_content('is a Drupal module.', 'is a Drupal ' . $extension_type . '.');
  replace_string_content('[EXTENSION_NAME]', $extension_machine_name);

  // Restore the attribution link.
  replace_string_content($attribution_link_token, $attribution_link);

  if ($repository !== NULL) {
    replace_string_content($repository_token, $repository);
  }

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
  @rename('your_extension.theme', $extension_machine_name . '.theme');
  @rename('config/schema/your_extension.schema.yml', 'config/schema/' . $extension_machine_name . '.schema.yml');
  @rename('src/Form/YourExtensionForm.php', 'src/Form/' . $extension_machine_name_class . 'Form.php');
  @rename('src/YourExtensionService.php', 'src/' . $extension_machine_name_class . 'Service.php');
  @rename('tests/src/Unit/YourExtensionServiceUnitTest.php', 'tests/src/Unit/' . $extension_machine_name_class . 'ServiceUnitTest.php');
  @rename('tests/src/Unit/YourExtensionPreprocessUnitTest.php', 'tests/src/Unit/' . $extension_machine_name_class . 'PreprocessUnitTest.php');
  @rename('tests/src/Kernel/YourExtensionFormKernelTest.php', 'tests/src/Kernel/' . $extension_machine_name_class . 'FormKernelTest.php');
  @rename('tests/src/Kernel/YourExtensionHooksKernelTest.php', 'tests/src/Kernel/' . $extension_machine_name_class . 'HooksKernelTest.php');
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
  @unlink('install-key.asc');
  @unlink('logo.svg');
  @unlink('.github/CODEOWNERS');

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

  remove_cspell_eddy_paths();
  normalize_cspell_words();
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
 * Parse the GitHub repository of the project.
 *
 * @param string $value
 *   The repository as 'owner/name', such as 'drevops/eddy', or an empty
 *   string.
 *
 * @return string|null
 *   The repository, or NULL when the value is empty.
 *
 * @throws \Exception
 *   When the value is not an 'owner/name' pair.
 */
function parse_github_repository(string $value): ?string {
  $value = trim($value);

  if ($value === '') {
    return NULL;
  }

  if (preg_match('#^[A-Za-z0-9][A-Za-z0-9-]*/[A-Za-z0-9._-]+$#', $value) !== 1 || in_array(basename($value), ['.', '..'], TRUE)) {
    throw new \Exception(sprintf('Invalid GitHub repository: "%s". Use the owner/name form, such as drevops/eddy.', $value));
  }

  return $value;
}

/**
 * Read the GitHub repository from a git remote URL.
 *
 * @param string $url
 *   The URL, such as 'git@github.com:drevops/eddy.git' or
 *   'https://github.com/drevops/eddy'.
 *
 * @return string|null
 *   The repository as 'owner/name', or NULL when the URL does not point to a
 *   repository on GitHub.
 */
function github_repository_from_url(string $url): ?string {
  if (preg_match('#^(?:[a-z][a-z0-9+.-]*://)?(?:[^@/]+@)?github\.com[:/]([A-Za-z0-9][A-Za-z0-9-]*)/([A-Za-z0-9._-]+?)(?:\.git)?/?$#', trim($url), $matches) !== 1) {
    return NULL;
  }

  return $matches[1] . '/' . $matches[2];
}

/**
 * Read the GitHub repository from the 'origin' remote.
 *
 * Only a repository whose root is the current directory counts: a copy of
 * the template inside another repository is not that repository's project.
 *
 * @return string|null
 *   The repository as 'owner/name', or NULL when the current directory is not
 *   the root of a git repository with an 'origin' remote on GitHub.
 */
function origin_github_repository(): ?string {
  $output = [];
  $exit_code = 0;
  exec('git rev-parse --show-toplevel 2>/dev/null', $output, $exit_code);
  $root = $output[0] ?? '';

  if ($exit_code !== 0 || $root === '' || realpath($root) !== realpath((string) getcwd())) {
    return NULL;
  }

  $output = [];
  exec('git remote get-url origin 2>/dev/null', $output, $exit_code);

  return $exit_code === 0 ? github_repository_from_url($output[0] ?? '') : NULL;
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
 * 'npm_specs()'. The text in the tool's 'replacements' is rewritten. Its
 * 'cspell_words' go later, in 'remove_unused_cspell_words()'.
 *
 * The 'AGENTS.md' note on the quality tools that run in the build names only
 * the languages of the PHP and JavaScript linters that stay.
 *
 * '.eddy/CLAUDE.md' lists the steps for adding a tool.
 *
 * @param array<string> $tools_remove
 *   The machine names of the tools to remove.
 */
function remove_tools(array $tools_remove): void {
  $tools_remove = add_dependent_tools($tools_remove);
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

    foreach ($spec['replacements'] ?? [] as $search => $replacement) {
      replace_string_content($search, $replacement);
    }
  }

  // The shared 'npm run lint' pipeline step covers ESLint and Stylelint;
  // remove it only when both are gone.
  $nodejs_lint = array_diff(['eslint', 'stylelint'], $tools_remove) !== [];

  if (!$nodejs_lint) {
    remove_tokens_with_content('DEV_NODEJS_LINT');
  }

  $php_lint = array_diff(['phpcs', 'phpstan', 'rector', 'twigcs'], $tools_remove) !== [];

  if (!$php_lint && !$nodejs_lint && in_array('cspell', $tools_remove, TRUE)) {
    remove_tokens_with_content('DEV_LINT');
  }

  $languages = ['PHP' => $php_lint, 'JavaScript' => $nodejs_lint];
  replace_string_content(quality_tools_note(array_keys($languages)), quality_tools_note(array_keys(array_filter($languages))));

  remove_npm($tools_remove);
}

/**
 * Add the tools that go with the tools to remove.
 *
 * FunctionalJavascript tests run on PHPUnit, so removing PHPUnit removes them.
 *
 * @param array<string> $tools_remove
 *   The machine names of the tools to remove.
 *
 * @return array<string>
 *   The machine names, with the tools that go with them.
 */
function add_dependent_tools(array $tools_remove): array {
  if (in_array('phpunit', $tools_remove, TRUE) && !in_array('functional_javascript', $tools_remove, TRUE)) {
    $tools_remove[] = 'functional_javascript';
  }

  return $tools_remove;
}

/**
 * Render the 'AGENTS.md' note on the quality tools that run in the build.
 *
 * @param array<string> $languages
 *   The languages of the quality tools, such as 'PHP', in the order the note
 *   names them.
 *
 * @return string
 *   The note line with its line break, or an empty string for no languages.
 */
function quality_tools_note(array $languages): string {
  if ($languages === []) {
    return '';
  }

  return '- The ' . join_list($languages) . ' quality tools run from within the `build/` directory' . "\n";
}

/**
 * Define the removal footprint for each selectable development tool.
 *
 * A block removes whole lines, so text it can't remove, such as a line that
 * names the tool among other items, is rewritten instead. 'replacements' maps
 * the template text to the text without the tool.
 *
 * JSON holds no markers, so 'cspell_words' lists the words that the tool
 * brings into the CSpell 'words' of '.cspell.json', such as the vendors of
 * its packages, when no other file uses them.
 *
 * @return array<string, array{
 *   token: string,
 *   files: list<string>,
 *   dirs: list<string>,
 *   composer_dev: list<string>,
 *   composer_allow_plugins: list<string>,
 *   composer_extra: list<string>,
 *   composer_scaffold_mappings?: list<string>,
 *   replacements?: array<string, string>,
 *   cspell_words?: list<string>,
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
      'cspell_words' => ['dealerdirect', 'phpcbf', 'phpcodesniffer', 'phpcompatibility', 'phpcs', 'squiz'],
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
      'cspell_words' => ['jangregor', 'mglaman'],
    ],
    'rector' => [
      'token' => 'DEV_RECTOR',
      'files' => ['rector.php'],
      'dirs' => [],
      'composer_dev' => ['palantirnet/drupal-rector'],
      'composer_allow_plugins' => [],
      'composer_extra' => [],
      'cspell_words' => ['palantirnet'],
    ],
    'twigcs' => [
      'token' => 'DEV_TWIGCS',
      'files' => ['.twig-cs-fixer.php'],
      'dirs' => [],
      'composer_dev' => ['vincentlanglet/twig-cs-fixer'],
      'composer_allow_plugins' => [],
      'composer_extra' => [],
      'cspell_words' => ['twigcsfixer', 'vincentlanglet'],
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
      'composer_dev' => [
        'phpunit/phpunit',
        'phpspec/prophecy-phpunit',
        'mikey179/vfsstream',
        // 'BrowserTestBase' runs Functional tests on Mink's BrowserKit driver.
        'behat/mink',
        'behat/mink-browserkit-driver',
        'symfony/browser-kit',
        'symfony/css-selector',
        'symfony/dom-crawler',
      ],
      'composer_allow_plugins' => [],
      'composer_extra' => [],
      'cspell_words' => ['behat', 'browserkit', 'codecov', 'mikey', 'phpspec', 'vfsstream'],
    ],
    'functional_javascript' => [
      'token' => 'DEV_FUNCTIONAL_JAVASCRIPT',
      'files' => [],
      'dirs' => ['tests/src/FunctionalJavascript'],
      'composer_dev' => ['lullabot/mink-selenium2-driver'],
      'composer_allow_plugins' => [],
      'composer_extra' => [],
      'replacements' => [
        'XDebug state, WebDriver port, build directory' => 'XDebug state, build directory',
        'PHPUnit tests (Unit/, FunctionalJavascript/)' => 'PHPUnit tests (Unit/)',
        ', plus a FunctionalJavascript smoke test example with the theme installed as the default theme' => '',
        ' and the browser that the FunctionalJavascript tests use,' => '',
        // The export opens a backslash-continued recipe line in the 'Makefile',
        // so a closing marker after it would join the shell command.
        "\t" . 'export WEBDRIVER_PORT="$$(vendor/bin/eddy-info webdriver-port)" && \\' . "\n" => '',
      ],
      'cspell_words' => ['chromedriver', 'headful', 'lullabot', 'webdriver'],
    ],
    'renovate' => [
      'token' => 'DEV_RENOVATE',
      'files' => ['renovate.json'],
      'dirs' => [],
      'composer_dev' => [],
      'composer_allow_plugins' => [],
      'composer_extra' => [],
      'cspell_words' => ['renovatebot'],
    ],
  ];
}

/**
 * Read a JSON file.
 *
 * @param string $file
 *   The file path.
 *
 * @return array<int|string, mixed>|null
 *   The decoded content, or NULL when the file is missing or holds no JSON
 *   object or array.
 */
function read_json_file(string $file): ?array {
  if (!file_exists($file)) {
    return NULL;
  }

  $raw = file_get_contents($file);

  if ($raw === FALSE) {
    // @codeCoverageIgnoreStart
    return NULL;
    // @codeCoverageIgnoreEnd
  }

  $content = json_decode($raw, TRUE, 512, JSON_THROW_ON_ERROR);

  return is_array($content) ? $content : NULL;
}

/**
 * Write a JSON file, indented with 4 spaces and ending with a new line.
 *
 * @param string $file
 *   The file path.
 * @param array<int|string, mixed> $content
 *   The content to encode.
 * @param bool $inline_lists
 *   Whether to write each list of scalars on 1 line, such as '["a", "b"]'.
 */
function write_json_file(string $file, array $content, bool $inline_lists = FALSE): void {
  $encoded = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

  if ($inline_lists) {
    $encoded = inline_json_lists($encoded);
  }

  if (file_put_contents($file, $encoded . PHP_EOL) === FALSE) {
    // @codeCoverageIgnoreStart
    throw new \RuntimeException('Unable to write ' . $file . '.');
    // @codeCoverageIgnoreEnd
  }
}

/**
 * Put each list of scalars in pretty-printed JSON on 1 line.
 *
 * A list that holds an object or another list stays expanded. 'json_encode()'
 * escapes the new lines inside strings, so each item of an expanded list is
 * 1 line.
 *
 * @param string $json
 *   The JSON, as 'JSON_PRETTY_PRINT' encodes it.
 *
 * @return string
 *   The JSON with each list of scalars on 1 line, such as '["a", "b"]'.
 */
function inline_json_lists(string $json): string {
  $scalar = '"(?:[^"\\\\]|\\\\.)*"|-?\d[\d.eE+-]*|true|false|null';

  return preg_replace_callback('/\[\n((?: +(?:' . $scalar . '),?\n)+) *\]/', static function (array $matches) use ($scalar): string {
    preg_match_all('/^ +(' . $scalar . '),?$/m', $matches[1], $items);

    return '[' . implode(', ', $items[1]) . ']';
  }, $json) ?? $json;
}

/**
 * Read 'composer.dev.json'.
 *
 * @return array<int|string, mixed>|null
 *   The decoded configuration, or NULL when the file is missing or holds no
 *   JSON object.
 */
function read_composer_dev_json(): ?array {
  return read_json_file('composer.dev.json');
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

  write_json_file('composer.dev.json', $config);
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
 * Build the URL of the GitHub archive of a template version.
 *
 * A commit after a tag has no tag of its own, so its archive is named after
 * the commit.
 *
 * @param string|null $version
 *   The version, such as '5.0.0' or '5.0.0-3-g1a2b3c4', or NULL for the
 *   default branch.
 *
 * @return string
 *   The URL of the '.tar.gz' archive.
 */
function template_archive_url(?string $version): string {
  $ref = $version ?? 'main';

  if (preg_match('/-\d+-g([0-9a-f]+)$/', $ref, $matches) === 1) {
    $ref = $matches[1];
  }

  return 'https://github.com/drevops/eddy/archive/' . $ref . '.tar.gz';
}

/**
 * Resolve the template archive to download and its checksum.
 *
 * 'INSTALL_TEMPLATE_ARCHIVE' takes precedence, with the checksum from
 * 'INSTALL_TEMPLATE_ARCHIVE_SHA256'. Otherwise a stamped URL comes with its
 * stamped checksum, and the GitHub archive of the version comes with none.
 *
 * @param string|null $version
 *   The template version, or NULL for the default branch.
 * @param string $url
 *   The stamped archive URL, or its placeholder.
 * @param string $sha256
 *   The stamped checksum, or its placeholder.
 *
 * @return array{string, string|null}
 *   The URL or the path of the archive, and its SHA-256 checksum, or NULL
 *   when no checksum is known.
 *
 * @throws \Exception
 *   When a checksum is not a SHA-256 checksum, or a stamped URL comes without
 *   one.
 */
function template_archive(?string $version, string $url, string $sha256): array {
  $override = (string) getenv('INSTALL_TEMPLATE_ARCHIVE');

  if ($override !== '') {
    $override_sha256 = (string) getenv('INSTALL_TEMPLATE_ARCHIVE_SHA256');
    $override_sha256 = $override_sha256 === '' ? NULL : template_archive_sha256($override_sha256, 'INSTALL_TEMPLATE_ARCHIVE_SHA256');

    return [$override, $override_sha256];
  }

  // An unstamped value keeps its '__' placeholder.
  if (str_starts_with($url, '__')) {
    return [template_archive_url($version), NULL];
  }

  return [$url, template_archive_sha256($sha256, 'The checksum of ' . $url)];
}

/**
 * Validate a SHA-256 checksum.
 *
 * @param string $sha256
 *   The checksum, in either case.
 * @param string $source
 *   What the checksum came from, for the error message.
 *
 * @return string
 *   The checksum in lowercase.
 *
 * @throws \Exception
 *   When the value is not 64 hexadecimal digits.
 */
function template_archive_sha256(string $sha256, string $source): string {
  if (preg_match('/^[0-9a-fA-F]{64}$/D', $sha256) !== 1) {
    throw new \Exception(sprintf('%s is not a SHA-256 checksum: "%s".', $source, $sha256));
  }

  return strtolower($sha256);
}

/**
 * Check whether a directory is empty, apart from the installer.
 *
 * Hidden entries, such as '.git', do not count: git, the operating system and
 * editors add them to a new directory.
 *
 * @param string $dir
 *   The directory.
 * @param string $installer
 *   The path of the installer.
 *
 * @return bool
 *   TRUE when the directory holds nothing but the installer and hidden
 *   entries, FALSE otherwise.
 */
function is_empty_directory(string $dir, string $installer): bool {
  foreach (scandir($dir) ?: [] as $entry) {
    if (!str_starts_with($entry, '.') && realpath($dir . DIRECTORY_SEPARATOR . $entry) !== realpath($installer)) {
      return FALSE;
    }
  }

  return TRUE;
}

/**
 * Install the template of the installer's version into an empty directory.
 *
 * The installer of the downloaded template asks the questions, so the template
 * and the code that rewrites it come from the same version.
 *
 * @param string $dir
 *   The directory.
 * @param string $installer
 *   The path of the running installer.
 * @param array<int, string> $args
 *   The arguments for the installer of the template.
 *
 * @throws \Exception
 *   When the download fails or does not match its checksum, or the installer
 *   of the template exits with an error.
 */
function install_from_archive(string $dir, string $installer, array $args): void {
  $version = template_version(TEMPLATE_VERSION);
  [$archive, $sha256] = template_archive($version, TEMPLATE_ARCHIVE_URL, TEMPLATE_ARCHIVE_SHA256);

  print sprintf('Downloading Eddy %s from %s', $version ?? 'development', $archive) . PHP_EOL;

  if ($sha256 === NULL) {
    print sprintf('Warning: No SHA-256 checksum is known for %s, so the template is not verified.', $archive) . PHP_EOL;
  }

  download_template($archive, $dir, $sha256);

  $exit_code = run_installer($dir, $args);

  if ($exit_code !== 0) {
    throw new \Exception(sprintf('The installer of the downloaded template exited with code %d.', $exit_code), $exit_code);
  }

  // The template replaced a copy saved as 'install.php', so only a copy saved
  // under another name is left behind.
  if (realpath(dirname($installer)) === realpath($dir) && basename($installer) !== 'install.php') {
    unlink($installer);
  }
}

/**
 * Download a template archive and extract it into a directory.
 *
 * The archive holds the template in 1 top-level directory, as GitHub builds
 * it, and the extraction strips that directory. The template is extracted
 * into a staging directory first, so a failed extraction leaves the directory
 * as it was.
 *
 * @param string $archive
 *   The URL or the path of the '.tar.gz' archive.
 * @param string $dir
 *   The directory.
 * @param string|null $sha256
 *   The SHA-256 checksum the archive must match, in lowercase, or NULL to
 *   skip the check.
 *
 * @throws \Exception
 *   When the archive cannot be downloaded, does not match the checksum or
 *   cannot be extracted.
 */
function download_template(string $archive, string $dir, ?string $sha256 = NULL): void {
  error_clear_last();
  $content = @file_get_contents($archive);

  if ($content === FALSE) {
    $reason = (string) preg_replace('/^file_get_contents\(.*?\): /', '', error_get_last()['message'] ?? '');

    throw new \Exception(rtrim(sprintf('Unable to download the template from %s. %s', $archive, $reason)));
  }

  $actual = hash('sha256', $content);

  if ($sha256 !== NULL && $actual !== $sha256) {
    throw new \Exception(sprintf('The template from %s does not match its SHA-256 checksum: expected %s, got %s.', $archive, $sha256, $actual));
  }

  // Hidden paths keep the directory empty for the next run when this run
  // stops before they are removed.
  $file = $dir . DIRECTORY_SEPARATOR . '.eddy-template.tar.gz';
  $staging = $dir . DIRECTORY_SEPARATOR . '.eddy-template';
  file_put_contents($file, $content);
  remove_dir($staging);
  mkdir($staging);

  $output = [];
  $exit_code = 0;
  exec(sprintf('tar -xzf %s --strip-components=1 -C %s 2>&1', escapeshellarg($file), escapeshellarg($staging)), $output, $exit_code);
  unlink($file);

  if ($exit_code !== 0) {
    remove_dir($staging);

    throw new \Exception(sprintf('Unable to extract the template from %s: %s', $archive, implode(PHP_EOL, $output)));
  }

  move_entries($staging, $dir);
  rmdir($staging);
}

/**
 * Move the entries of a directory into another directory.
 *
 * A directory in both is merged, the way 'tar' extracts into an existing
 * directory, and any other entry replaces the entry of the same name.
 *
 * @param string $src
 *   The directory to move the entries from.
 * @param string $dst
 *   The directory to move the entries into.
 */
function move_entries(string $src, string $dst): void {
  foreach (array_diff(scandir($src) ?: [], ['.', '..']) as $entry) {
    $from = $src . DIRECTORY_SEPARATOR . $entry;
    $to = $dst . DIRECTORY_SEPARATOR . $entry;

    if (is_dir($from) && !is_link($from) && is_dir($to) && !is_link($to)) {
      move_entries($from, $to);
      rmdir($from);

      continue;
    }

    rename($from, $to);
  }
}

/**
 * Run the installer of a template in its directory.
 *
 * The installer gets the terminal, the environment and the arguments of this
 * process, so it reads the same answers.
 *
 * @param string $dir
 *   The template directory.
 * @param array<int, string> $args
 *   The arguments for the installer.
 *
 * @return int
 *   The exit code of the installer.
 *
 * @throws \Exception
 *   When the directory holds no installer.
 */
function run_installer(string $dir, array $args): int {
  if (!is_file($dir . DIRECTORY_SEPARATOR . 'install.php')) {
    throw new \Exception(sprintf('The template in %s holds no install.php.', $dir));
  }

  $process = proc_open([PHP_BINARY, 'install.php', ...$args], [STDIN, STDOUT, STDERR], $pipes, $dir);

  if ($process === FALSE) {
    // @codeCoverageIgnoreStart
    throw new \Exception('Unable to run the installer of the template.');
    // @codeCoverageIgnoreEnd
  }

  return proc_close($process);
}

/**
 * Remove the npm footprint of deselected JavaScript tools.
 *
 * Each deselected tool loses its 'devDependencies' and scripts in
 * 'package.json'. The aggregate 'lint' and 'lint-fix' scripts chain
 * per-language sub-scripts; after dropping a tool's sub-scripts they are
 * rebuilt from the survivors (or removed when none remain).
 *
 * Without any tool from 'npm_specs()' nothing runs npm, so 'package.json',
 * 'package-lock.json' and the '#;< DEV_NPM' blocks go instead, along with the
 * npm configuration of 'renovate.json'. The 'DEBUG' note in 'AGENTS.md' stops
 * naming npm.
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

  if (array_diff(array_keys($npm_specs), $remove) === []) {
    remove_tokens_with_content('DEV_NPM');
    replace_string_content('(Composer, npm, Drush)', '(Composer, Drush)');
    @unlink('package.json');
    @unlink('package-lock.json');
    remove_renovate_npm();

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
 * Remove the npm configuration from 'renovate.json'.
 *
 * 'npm' goes from 'enabledManagers' and from the 'matchManagers' of each
 * package rule, and a rule left with no manager goes with it.
 *
 * The 'lockFileMaintenance' block goes once every manager left has no lock
 * file. An empty or missing 'enabledManagers' enables every manager, so the
 * block stays then.
 *
 * Each list of scalars stays on 1 line, as the template writes it, so the
 * file changes only where npm goes.
 */
function remove_renovate_npm(): void {
  $file = 'renovate.json';
  $config = read_json_file($file);

  if ($config === NULL) {
    return;
  }

  $managers_without_lock_file = ['github-actions'];
  $without_npm = static fn(array $managers): array => array_values(array_filter($managers, static fn(mixed $manager): bool => $manager !== 'npm'));
  $updated = $config;

  if (is_array($updated['enabledManagers'] ?? NULL)) {
    $updated['enabledManagers'] = $without_npm($updated['enabledManagers']);
  }

  if (is_array($updated['packageRules'] ?? NULL)) {
    $rules = [];

    foreach ($updated['packageRules'] as $rule) {
      if (is_array($rule) && is_array($rule['matchManagers'] ?? NULL) && in_array('npm', $rule['matchManagers'], TRUE)) {
        $rule['matchManagers'] = $without_npm($rule['matchManagers']);

        if ($rule['matchManagers'] === []) {
          continue;
        }
      }

      $rules[] = $rule;
    }

    $updated['packageRules'] = $rules;
  }

  $managers = is_array($updated['enabledManagers'] ?? NULL) ? $updated['enabledManagers'] : [];
  $managers_with_lock_file = array_filter($managers, static fn(mixed $manager): bool => !in_array($manager, $managers_without_lock_file, TRUE));

  if ($managers !== [] && $managers_with_lock_file === []) {
    unset($updated['lockFileMaintenance']);
  }

  if ($updated === $config) {
    return;
  }

  write_json_file($file, $updated, inline_lists: TRUE);
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
    $removed = array_map(static fn(string $line): bool => str_starts_with($line, $token), $lines);
    file_put_contents($file, implode("\n", remove_lines($lines, $removed)));
  }
}

/**
 * Remove blocks between token markers from project files.
 *
 * Removes all content between lines containing "#;< TOKEN" and "#;> TOKEN"
 * markers, inclusive. 'get_marker_files()' lists the files it walks.
 *
 * @param string $token
 *   The token name used in the markers.
 */
function remove_tokens_with_content(string $token): void {
  $start_marker = '#;< ' . $token;
  $end_marker = '#;> ' . $token;

  foreach (get_marker_files() as $file) {
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
    $removed = [];
    $inside = FALSE;

    foreach ($lines as $line) {
      if (str_contains($line, $start_marker)) {
        $inside = TRUE;
        $removed[] = TRUE;
        continue;
      }

      if (str_contains($line, $end_marker)) {
        $inside = FALSE;
        $removed[] = TRUE;
        continue;
      }

      $removed[] = $inside;
    }

    file_put_contents($file, implode("\n", remove_lines($lines, $removed)));
  }
}

/**
 * Remove lines without leaving a run of blank lines behind.
 *
 * A removal between 2 blank lines keeps only 1 of them, and a removal at the
 * start or the end of the content keeps no blank line there. A removed block
 * with a blank line on each side leaves a single one.
 *
 * @param array<int, string> $lines
 *   The lines.
 * @param array<int, bool> $removed
 *   Whether each line goes, keyed like the lines.
 *
 * @return array<int, string>
 *   The lines that stay.
 */
function remove_lines(array $lines, array $removed): array {
  $kept = [];
  // The start of the content counts as a blank line.
  $after_blank = TRUE;
  $after_removal = FALSE;

  foreach ($lines as $index => $line) {
    if ($removed[$index] ?? FALSE) {
      $after_removal = TRUE;
      continue;
    }

    $blank = trim($line) === '';

    if (!$after_removal || !$blank || !$after_blank) {
      $kept[] = $line;
      $after_blank = $blank;
    }

    $after_removal = FALSE;
  }

  return $kept;
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
 *
 * 'get_marker_files()' lists the files it walks.
 */
function remove_special_comments(): void {
  foreach (get_marker_files() as $file) {
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
    $removed = array_map(static fn(string $line): bool => str_contains($line, '#;'), $lines);
    file_put_contents($file, implode("\n", remove_lines($lines, $removed)));
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
  $config = read_json_file('.cspell.json');
  $raw_words = $config['words'] ?? NULL;

  if ($config === NULL || !is_array($raw_words)) {
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

  write_json_file('.cspell.json', $config);
}

/**
 * Remove the paths inside '.eddy' from the CSpell `ignorePaths`.
 *
 * A generated project has no '.eddy' directory, so these paths ignore
 * nothing there.
 */
function remove_cspell_eddy_paths(): void {
  $config = read_json_file('.cspell.json');
  $paths = $config['ignorePaths'] ?? NULL;

  if ($config === NULL || !is_array($paths)) {
    return;
  }

  $kept = array_values(array_filter($paths, static fn(mixed $path): bool => !is_string($path) || preg_match('#^/?\.eddy(/|$)#', $path) !== 1));

  if (count($kept) === count($paths)) {
    return;
  }

  $config['ignorePaths'] = $kept;

  write_json_file('.cspell.json', $config);
}

/**
 * List the CSpell words of the tools to remove.
 *
 * @param array<string> $tools_remove
 *   The machine names of the tools to remove.
 *
 * @return list<string>
 *   The 'cspell_words' of the tools and of the tools that go with them.
 */
function tool_cspell_words(array $tools_remove): array {
  $specs = tool_specs();
  $words = [];

  foreach (add_dependent_tools($tools_remove) as $tool) {
    $words = [...$words, ...($specs[$tool]['cspell_words'] ?? [])];
  }

  return $words;
}

/**
 * List the CSpell words of the extension types to remove.
 *
 * @param array<string> $types
 *   The extension types to remove.
 *
 * @return list<string>
 *   The 'cspell_words' of the types.
 */
function extension_type_cspell_words(array $types): array {
  $specs = extension_type_specs();
  $words = [];

  foreach ($types as $type) {
    $words = [...$words, ...($specs[$type]['cspell_words'] ?? [])];
  }

  return $words;
}

/**
 * Remove words from the CSpell 'words' of '.cspell.json' that no file uses.
 *
 * A word stays while a project file still uses it, such as a word in the
 * extension name or in a kept 'install.php'.
 *
 * @param array<string> $words
 *   The words to remove once unused.
 */
function remove_unused_cspell_words(array $words): void {
  $config = read_json_file('.cspell.json');
  $cspell_words = $config['words'] ?? NULL;

  if ($config === NULL || !is_array($cspell_words) || $words === []) {
    return;
  }

  $used = [];

  foreach (get_files() as $file) {
    if (basename($file) !== '.cspell.json') {
      $used += cspell_text_words((string) file_get_contents($file));
    }
  }

  $unused = array_diff(array_map(strtolower(...), $words), array_keys($used));
  $kept = array_values(array_filter($cspell_words, static fn(mixed $word): bool => !is_string($word) || !in_array(strtolower($word), $unused, TRUE)));

  if ($kept === $cspell_words) {
    return;
  }

  $config['words'] = $kept;
  write_json_file('.cspell.json', $config);
}

/**
 * List the words of a text that CSpell may check.
 *
 * Each run of letters counts whole and split at its camel case boundaries, in
 * lower case, so the list holds every word of letters alone that CSpell
 * checks in the text.
 *
 * @param string $text
 *   The text.
 *
 * @return array<string, true>
 *   The words, as keys.
 */
function cspell_text_words(string $text): array {
  $words = [];

  foreach (preg_split('/[^A-Za-z]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $run) {
    $words[strtolower($run)] = TRUE;

    foreach (preg_split('/(?<=[a-z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', $run, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
      $words[strtolower($part)] = TRUE;
    }
  }

  return $words;
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
 * Get the template files that may hold markers.
 *
 * The root 'install.php' is left out. Its code spells out the marker syntax,
 * so stripping marker lines from it would delete code.
 *
 * @return array<string>
 *   Array of file paths.
 */
function get_marker_files(): array {
  $installer = getcwd() . DIRECTORY_SEPARATOR . 'install.php';

  return array_values(array_filter(get_files(), static fn(string $file): bool => $file !== $installer));
}

/**
 * Check whether a project path belongs to the template.
 *
 * The '.git', '.idea', '.chromedriver', 'vendor' and 'node_modules'
 * directories do not, at any depth. Neither does anything in '.claude' except
 * 'settings.json', nor any path that the '.gitignore' rules ignore.
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
  if ($is_dir && in_array(basename($path), ['.git', '.idea', '.chromedriver', 'vendor', 'node_modules'], TRUE)) {
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

/**
 * Remove a file and each parent directory that the removal leaves empty.
 *
 * @param string $file
 *   The file path, relative to the current directory.
 */
function remove_file(string $file): void {
  @unlink($file);

  $dir = dirname($file);

  while ($dir !== '.' && is_dir($dir) && scandir($dir) === ['.', '..']) {
    rmdir($dir);
    $dir = dirname($dir);
  }
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
