<?php

#exit;

		include_once 'rpglife/scoring.php';
		include_once 'rpglife/task.php';
		include_once 'rpglife/todo_cmd.php';
		include_once 'robot.cfg';

include_once('rocket-chat-rest-client/httpful.phar');
include_once 'rocket-chat-rest-client/src/RocketChatClient.php';
include_once 'rocket-chat-rest-client/src/RocketChatUser.php';
include_once 'rocket-chat-rest-client/src/RocketChatChannel.php';
include_once 'rocket-chat-rest-client/src/RocketChatGroup.php';
include_once 'rocket-chat-rest-client/src/RocketChatSettings.php';
include_once 'rocket-chat-rest-client/src/RocketChatWebhuk.php';

define('REST_API_ROOT', '/api/v1/');
define('ROCKET_CHAT_INSTANCE', 'https://jabber.stnn.ru');


class ToDo {
	public $id;
	public $text;
	public $data_create;
	public $data_done;
	public $done;
	public $data_due;
	public $data_treshold;
	public $list_project=array();
	public $list_context=array();
	public $priority;
	
	public function __construct($raw_text)
	{
		$this->$text = trim($raw_text);
	}
}


function debug($text,$var=false)
{
	$file='robot.log';
	if (!$var)
		$message=$text;
	else
		$message=var_export($text,true);

	file_put_contents($file, $message."
", FILE_APPEND);
}

function history_autoclear_save($user,$text)
{
	$roomID=get_cur_roomid(get_json());
	$file='/usr/data/script/rocket_chat/crontab/clear_history'.$user."_".$roomID.'.txt';
	file_put_contents($file, $text.'
', FILE_APPEND);
}
function todo_save($text)
{
	$roomID=get_cur_roomid(get_json());
	$file='/usr/data/script/todo/'.$roomID.'.txt';
        file_put_contents($file, $text.'
', FILE_APPEND);
	todo_sort();
}

function todo_backup()
{
	$date=date('Y-m-d_H-i-s');
	$roomID=get_cur_roomid(get_json());
	$file='/usr/data/script/todo/'.$roomID.'.txt';
	$file_backup='/usr/data/script/todo/backup/'.$roomID.'.txt.'.$date;
	copy($file,$file_backup);

	$file_backup='/usr/data/script/todo/backup/'.$roomID.'.txt';
	copy($file,$file_backup);
}

function todo_restore()
{
	$date=date('Y-m-d_H-i-s');
	$roomID=get_cur_roomid(get_json());
	$file='/usr/data/script/todo/'.$roomID.'.txt';
	$file_backup='/usr/data/script/todo/backup/'.$roomID.'.txt.'.$date;
	copy($file,$file_backup);

	$file_backup='/usr/data/script/todo/backup/'.$roomID.'.txt';
	copy($file_backup,$file);
}

/*
Duplication function format
function priority_mark($task)
{
	$string = $task;
	
$pattern[] = '/ (\([A-Z]\))/i';
$pattern[] = '/ (\+[\S]+)/i';

$replacement = ' **${1}**';


$result=preg_replace($pattern, $replacement, $string);

debug("Message replace:".$string."
".var_export($result,true)."
".var_export($pattern,true)."
".var_export($replacement,true));

return $result;
}

*/

function todo_list($full=false,$filter="",$filter_score=""){
	$roomID=get_cur_roomid(get_json());
        $file='/usr/data/script/todo/'.$roomID.'.txt';
	$file_array = file($file);
	$count['all']=0;
	$count['x']=0;
	if ($filter!="")
	{
		if (strpos($filter,"score bad")!==false)
		{
			
			$filter_score=0;
			$filter=trim(str_replace("score bad","",$filter));
		}
		$pattern = "/^(h\d{4})/";
		if (preg_match($pattern, $filter, $matches) == 1) {
			$filter_hash=$matches[1];
			$filter="";
		}
		$filter_format=todo_format_pre($filter,true);
		$todo_list[]="**Used filter:** ".$filter." : ".str_replace('|','\|',$filter_format);
		$filter=$filter_format;
	}
		$task_list= new Task_List($file);
		$score_count= new Score;
	for($i=0;$i<count($file_array);$i++)
	{
		if (($full==false) && (strpos($file_array[$i],"x ")===0))
			continue;
		if ($filter!="")
#			if (strpos($file_array[$i],$filter)===false)
			if (preg_match('/'.$filter.'/',$file_array[$i]) == 0)
				continue;
		if ($filter_score!="")
		{
			if ($score_count->getScoreTask($task_list->task[$i])['total']>$filter_score)
				continue;
		}
		if (isset($filter_hash))
		{
			if ($task_list->task[$i]->hash()!=$filter_hash)
				continue;
		}

		$count['all']++;
		if (strpos($file_array[$i],"x ")===0)
			$count['x']++;
		$todo_list[]=trim($i+1 . ": " . trim($file_array[$i]). " score:".$score_count->getScoreTask($task_list->task[$i])['total']." hash:".$task_list->task[$i]->hash());
#		$todo_list[]=trim($i+1 . ": " . trim($file_array[$i]));
	}
/*	else
	{
		$todo_list=$file_array;
	}
*/
/*	return '
1. '.implode ('
1. ',$todo_list);
*/

		$task_list= new Task_List($file,$full,$filter);
#		$score=scoring_tasks($task_list->task);

$score=0;
$score=$score_count->getScoreTaskList($task_list->task);
/*
for($i=0;$i<count($task_list->task);$i++)
	{
		$score_cur=$task_list->task[$i]->getScore();
		$score+=$score_cur;
}
*/
$projects_string="";
$contexts_string="";
	if (count($score['projects'])>0)
	{
	# Вывод распределения по проектам
		$projects_string="
| Project | Score |
| ---- | ---- |
";
		foreach ($score['projects'] as $key => $value)
			$projects_string=$projects_string."| ".$key." | ".$value." |
";
		$projects_string=$projects_string."| **total** | **".$score['total']."** |
";
	}
	if (count($score['contexts'])>0)
	{
		$contexts_string="
| Context | Score |
| ---- | ---- |
";
		foreach ($score['contexts'] as $key => $value)
			$contexts_string=$contexts_string."| ".$key." | ".$value." |
";
	}

$task_list_table="

| Task |
| ---- |";

for ($i=0;$i<count($todo_list);$i++)
	$task_list_table=$task_list_table."
| ".todo_format($todo_list[$i])." |";

debug("projects_string:".var_export($projects_string,true));
return $task_list_table."

**Total: x".$count['x']."/+".$count['all']."/".($count['all']-$count['x'])."**
**Score: ".$score['total']."**
".$projects_string."
".$contexts_string;
#."
#".var_export($task_list->task, true);

}

function todo_format($todo_list)
{
	$string = $todo_list;
	$pattern[] = '/ (@\S+)/i';
	$replacement[] = ' **${1}**';

	$pattern[] = '/ (\+\S+)/i';
	$replacement[] = ' __${1}__';

	$pattern[] = '/ (\([A-Z]\))/i';
	$replacement[] = ' **${1}**';

	$pattern[] = '/ (status:\S+)/i';
	$replacement[] = ' **${1}**';

	$pattern[] = '/ (step:\S+)/i';
	$replacement[] = ' **${1}**';

	$pattern[] = '/ (progress:\w+)/i';
	$replacement[] = ' **${1}**';

	$pattern[] = '/ (due:)([0-9-]*)/i';
	$replacement[] = ' **${1}**__${2}__';

        $pattern[] = '/ (t:)([0-9-]*)/i';
        $replacement[] = ' **${1}**__${2}__';

	$pattern[] = '/ (rec:)([0-9]*[dwmyb])/i';
	$replacement[] = ' **${1}**__${2}__';


	// Зачеркиваем выполненные задачи
        $pattern[] = '/([0-9]*:) (x [0-9-]* .*)/i';
        $replacement[] = '~~${1} ${2}~~';


	ksort($patterns);
	ksort($replacements);
	$todo_formated=preg_replace($pattern, $replacement, $string);
	return $todo_formated;
}

function todo_format_pre($todo_text,$full_text=false)
{
        $string = $todo_text;
	$date_today=date('Y-m-d');
	$date_now=date('Y-m-d_H-i-s');

//	$date_tomorrow=date('Y-m-d',mktime(0, 0, 0, date("m")  , date("d")+1, date("Y")));
	$date_tomorrow=date('Y-m-d',strtotime("+1 day"));


// дни недели
	$data_monday=date('Y-m-d',strtotime("next Monday"));
	$data_tuesday=date('Y-m-d',strtotime("next Tuesday"));
	$data_wednesday=date('Y-m-d',strtotime("next Wednesday"));
	$data_thursday=date('Y-m-d',strtotime("next Thursday"));
	$data_friday=date('Y-m-d',strtotime("next Friday"));
	$data_saturday=date('Y-m-d',strtotime("next Saturday"));
	$data_sunday=date('Y-m-d',strtotime("next Sunday"));
// Конец дней недели


//date('Y-m-d',mktime(0, 0, 0, date("m")  , date("d")+1, date("Y")));
	$date_month=date('Y-m-d',mktime(0, 0, 0, date("m")+1  , 0, date("Y")));
	$date_month_next=date('Y-m-d',mktime(0, 0, 0, date("m")+2  , 0, date("Y")));

	$date_week=date('Y-m-d',strtotime("friday this week"));
	$date_week_next=date('Y-m-d',strtotime("next friday +1 week"));

	$pattern[] = '/ (test):(now|сейчас)/i';
	$replacement[] = ' ${1}:'.$date_now;


	$pattern[] = '/ (due|t):(today|сегодня)/i';
	$replacement[] = ' ${1}:'.$date_today;

        $pattern[] = '/ (due|t):(tomorrow|завтра)/i';
        $replacement[] = ' ${1}:'.$date_tomorrow;

	$pattern[] = '/ (due|t):(Monday|понедельник|пн)/i';
	$replacement[] = ' ${1}:'.$data_monday;

	$pattern[] = '/ (due|t):(Tuesday|вторник|вт)/i';
	$replacement[] = ' ${1}:'.$data_tuesday;

	$pattern[] = '/ (due|t):(Wednesday|среда|ср)/i';
	$replacement[] = ' ${1}:'.$data_wednesday;

	$pattern[] = '/ (due|t):(Thursday|четверг|чт)/i';
	$replacement[] = ' ${1}:'.$data_thursday;

	$pattern[] = '/ (due|t):(Friday|пятница|пт)/i';
	$replacement[] = ' ${1}:'.$data_friday;

	$pattern[] = '/ (due|t):(Saturday|суббота|сб)/i';
	$replacement[] = ' ${1}:'.$data_saturday;

	$pattern[] = '/ (due|t):(Sunday|воскресенье|вс)/i';
	$replacement[] = ' ${1}:'.$data_sunday;



        $pattern[] = '/ (due|t):(month|месяц)/i';
        $replacement[] = ' ${1}:'.$date_month;

        $pattern[] = '/ (due|t):(next_month|следующий_месяц)/i';
        $replacement[] = ' ${1}:'.$date_month_next;

        $pattern[] = '/ (due|t):(week|неделя)/i';
        $replacement[] = ' ${1}:'.$date_week;

        $pattern[] = '/ (due|t):(next_week|следующая_неделя)/i';
        $replacement[] = ' ${1}:'.$date_week_next;

	if ($full_text==true)
	{
		$pattern[] = '/(today|сегодня)/i';
	        $replacement[] = $date_today;

		$pattern[] = '/(tommorrow|завтра)/i';
		$replacement[] = $date_tomorrow;

		$pattern[] = '/(yesterday|вчера)/i';
		$replacement[] = date('Y-m-d', strtotime('-1 days'));

		$pattern[] = '/(week|неделя)/i';
			$day = date('w')-1;
		$date_cur_week=date('Y-m-d', strtotime('-'.$day.' days'));
		for ($i=-1;$i>-7;$i--)
		{
			$date_cur_week=$date_cur_week."|".date('Y-m-d', strtotime('-'.$day-$i.' days'));
		}
		$replacement[] = $date_cur_week;
		
		$pattern[] = '/(month|месяц)/i';
                $replacement[] = date('Y-m-');;


	}

        ksort($patterns);
        ksort($replacements);

	$todo_formated=preg_replace($pattern, $replacement, $string);
	return $todo_formated;
}

function todo_done($id) 
{
#	$id=2;
	$roomID=get_cur_roomid(get_json());
        $file='/usr/data/script/todo/'.$roomID.'.txt';
        $file_array = file($file);
	if (strpos($file_array[$id],"x ")===0)
	{
		$id++;
		return "Task #".$id." already mark done.";
	}
	if (recurrence($file_array[$id])!==false)
		$file_array[]=recurrence($file_array[$id]);
	$todo_text='x '.date('Y-m-d').' '.trim($file_array[$id])."\n";
	$file_array[$id]=$todo_text;
	sort($file_array);
	file_put_contents($file, implode ('',$file_array));
	return $todo_text;
}

function change_date($date,$period)
{
	$cur_date=strtotime($date.$period);
	return date( "Y-m-d", $cur_date);;
}

function recurrence($text)
{
	$pattern = '/\brec:(?P<count>[0-9]*)(?P<period>[dwmy])\b/i';
#debug($text);
//	$new_text="";
	$date_today=date('Y-m-d');
	if (!preg_match($pattern,$text,$result))
	{
		return false;
	}
		switch ($result['period']) {
			case "d": $period="day"; break;
			case "w": $period="week"; break;
			case "m": $period="month"; break;
			case "y": $period="year"; break;
		}
		$tag="due";
		$pattern_tag = '/\b'.$tag.':(?P<date>[0-9-]*)\b/i';
		if (preg_match($pattern_tag,$text,$result_tag))
		{
			$date_tag=$result_tag['date'];
			$date_tag_new=change_date($date_tag, "+".$result['count']." ".$period);
			$text=str_replace($tag.":".$date_tag,$tag.":".$date_tag_new,$text);
		}
		$tag="t";
		$pattern_tag = '/\b'.$tag.':(?P<date>[0-9-]*)\b/i';
		if (preg_match($pattern_tag,$text,$result_tag))
		{
			$date_tag=$result_tag['date'];
			$date_tag_new=change_date($date_tag, "+".$result['count']." ".$period);
			$text=str_replace($tag.":".$date_tag,$tag.":".$date_tag_new,$text);
		}
		
		## Replace old adding date to cur date
		$text=preg_replace("/(^|\([A-Z]\)) ?[0-9-]* /","$1 ".$date_today." ",$text);
		
		return trim($text)."
";
}
function todo_get_text($id)
{
	$roomID=get_cur_roomid(get_json());
	$file='/usr/data/script/todo/'.$roomID.'.txt';
	$file_array = file($file);
	return $file_array[$id-1];
}

function todo_get_id($text)
{
	$roomID=get_cur_roomid(get_json());
	$file='/usr/data/script/todo/'.$roomID.'.txt';
	$file_array = file($file);
	for($i=0;$i<count($file_array);$i++)
        {
                if (strcasecmp(trim($file_array[$i]),trim($text))==0)
			return $i+1;
	}
	return false;
}


function todo_sort() 
{
	$roomID=get_cur_roomid(get_json());
        $file='/usr/data/script/todo/'.$roomID.'.txt';
        $file_array = file($file);
	$file_array = array_map('ltrim', $file_array);

	for($i=0;$i<count($file_array);$i++)
        {
                if ((strlen($file_array[$i])===0))
		unset($file_array[$i]);
	}

	sort($file_array);

	file_put_contents($file, implode ('',$file_array));
}

function todo_change($text_todo)
{
	$roomID=get_cur_roomid(get_json());
        $file='/usr/data/script/todo/'.$roomID.'.txt';
        $file_array = file($file);
	$text_todo=trim($text_todo);
	$id=substr($text_todo,0,strpos($text_todo,' '));
	$id--;
	$text_todo=todo_format_pre(trim(substr($text_todo,strpos($text_todo,' '))));
	
        $file_array[$id]="".$text_todo."\n";
        sort($file_array);
        file_put_contents($file, implode ('',$file_array));
}

function todo_change_tag($text_todo,$new_tag="",$user="1")
{
	$new_tag=trim($new_tag);
	$tag=substr($new_tag,0,strpos(new_tag,':'));

	$roomID=get_cur_roomid(get_json());
        $file='/usr/data/script/todo/'.$roomID.'.txt';
        $file_array = file($file);
        $text_todo=trim($text_todo);
        $id=substr($text_todo,0);
        $id--;
        $text_todo=trim($file_array[$id]);

	if (strpos($text_todo,' '.$tag.':')>1)
		$text_todo=preg_replace("/ (".$tag.":\S+)/i", " ".$new_tag." ", $text_todo);
	else
		$text_todo=$text_todo."  ".$new_tag;
	sort($file_array);
        file_put_contents($file, implode ('',$file_array));
}

function todo_status_change($text_todo,$new_status="",$user="1")
{
	$roomID=get_cur_roomid(get_json());
        $file='/usr/data/script/todo/'.$roomID.'.txt';
        $file_array = file($file);
	$text_todo=trim($text_todo);
	$id=substr($text_todo,0);
	$id--;
	$text_todo=trim($file_array[$id]);
	if (strpos($text_todo,' status:')>1)
		$text_todo=preg_replace("/ (status:\w+)/i", " status:".$new_status." ", $text_todo);
	else 	
		$text_todo=$text_todo." status:".$new_status;

//   Надо обдумать как будут отрабатывать повторяющиеся задачи

	if (strcmp($new_status,"finish")==0)
	{
		if (recurrence($file_array[$id])!==false)
	                $file_array[]=recurrence($file_array[$id]);
		$file_array[$id]="x ".date('Y-m-d')." ".$text_todo." ".$new_status."_d:".date('Y-m-d')."\n";
	}
	else
	        $file_array[$id]="".$text_todo." ".$new_status."_d:".date('Y-m-d')."\n";
//        $file_array[$id]="".$text_todo." status_by:@".$user."\n";
        sort($file_array);
        file_put_contents($file, implode ('',$file_array));
}


function todo_del($id)
{
#       $id=2;
        $roomID=get_cur_roomid(get_json());
        $file='/usr/data/script/todo/'.$roomID.'.txt';
        $file_array = file($file);
	$text_todo =trim($file_array[$id]);
	unset($file_array[$id]);
        sort($file_array);
        file_put_contents($file, implode ('',$file_array));
	return $text_todo;
}


function todo_clear()
{
        $roomID=get_cur_roomid(get_json());
        $file='/usr/data/script/todo/'.$roomID.'.txt';
        file_put_contents($file, '');
}


function json_request($API,$request)
{

$SERVER="https://jabber.stnn.ru";

$request=json_encode($request);

$ch = curl_init($SERVER.'/api/v1/'.$API);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Content-Length: ' . strlen($request))
);
        $result = json_decode(curl_exec($ch));
	return $result;
}

function json_request_a($AUTH,$API,$request)
{

$SERVER="https://jabber.stnn.ru";

$request=json_encode($request);

$ch = curl_init($SERVER.'/api/v1/'.$API);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
	'X-Auth-Token: '.$AUTH['authToken'],
	'X-User-Id: '.$AUTH['userId'],
    'Content-Length: ' . strlen($request))

);
/*
	debug(array(
    'Content-Type: application/json',
        'X-Auth-Token: '.$AUTH['userId'],
        'X-User-Id: '.$AUTH['authToken'],
    'Content-Length: ' . strlen($request)));
*/
        $result = json_decode(curl_exec($ch));
        return $result;
}


function cron_add($roomID)
{
	$get_messages=get_json();
        $USER_ID=$get_messages['user_id'];
        $file='/usr/data/script/rocket_chat/crontab/'.$USER_ID."_".$roomID.'.txt';

        $roomID=get_cur_roomid(get_json());
	$text=var_export(get_json(),true);
        #$file='/usr/data/script/cron/'.$roomID.'.txt';
        file_put_contents($file, $text.'
', FILE_APPEND);

}

function destroy_room($authToken,$ROOM_ID)
{
        $data = array("roomId" => $ROOM_ID);
        $result =  json_request_a($authToken,"groups.close",$data);
        return $result;
}


function get_json()
{
	$postData = file_get_contents('php://input');
//	debug($postData);
        $get_date=json_decode($postData, true);
	return $get_date;
}

function get_cur_roomid($json)
{
	return $json['channel_id'];
}
function get_message($json)
{	
        return $json['text'];
}

function check_cmd($text)
{
	$text=strtolower($text);
	$cmd="";
	if (strpos($text,"help")===0) $cmd="help";
	if (strpos($text,"clear history")===0) $cmd="clear history";
	if (strpos($text,"history clear")===0) $cmd="clear history";
	if (strpos($text,"history clear old")===0) $cmd="clear history old";
	if (strpos($text,"clear history old")===0) $cmd="clear history old";
	if (strpos($text,"todo")===0) $cmd="todo";
	if (strpos($text,"todo +")===0) $cmd="todo +";
	if (strpos($text,"todo list")===0) $cmd="todo list";
	if (strpos($text,"todo list all")===0) $cmd="todo list all";

	if (strpos($text,"todo done")===0) $cmd="todo done";
	if (strpos($text,"todo done")===0) $cmd="todo done";
	if (strpos($text,"todo x")===0) $cmd="todo x";
	if (strpos($text,"todo del")===0) $cmd="todo del";
	if (strpos($text,"todo -")===0) $cmd="todo -";
	if (strpos($text,"todo clear")===0) $cmd="todo clear";
	if (strpos($text,"destroy room")===0) $cmd= "destroy room";
	if (strpos($text,"todo *")===0) $cmd= "todo *";
	if (strpos($text,"todo **")===0) $cmd= "todo **";
	if (strpos($text,"todo change")===0) $cmd= "todo change";
	if (strpos($text,"todo start")===0) $cmd= "todo start";
	if (strpos($text,"todo stop")===0) $cmd= "todo stop";
	if (strpos($text,"todo step")===0) $cmd= "todo step";
	if (strpos($text,"todo finish")===0) $cmd= "todo finish";
	if (strpos($text,"todo backup")===0) $cmd= "todo backup";
	if (strpos($text,"todo restore")===0) $cmd= "todo restore";
	
	if (strpos($text,"cron_add")===0) $cmd= "cron_add";
	if (strpos($text,"cron_history_clear")===0) $cmd= "history_autoclear_save";
	
	return $cmd;
}

function todo_help()
{
	return "
###  **todo** - Управление задачами
В разработке. За основу взято [Todo.txt](http://todotxt.org/). Описание формата [тут](https://github.com/todotxt/todo.txt). Пока реализованы задачи для канала.

#### Ньюансы
* Задачи привязаны к комнатам/чатам
* При очистке истории комнаты/чата задачи не удаляются

#### Команды
* **todo (+|add) Текст задачи** - Создание новой задачи
* **todo list _строка фильтра_** - список задач, исключая выполненные с фильтром (regexp) по данной строке. Если фильтр пустой, то выводит все задачи.
* **todo list all _строка фильтра_** - список всех задач, включая выполненные с фильтром (regexp) по данной строке. Если фильтр пустой, то выводит все задачи.
* **todo list __score bad__ _строка фильтра_** - аналогично верхнему, но выводит только просроченные задачи
* **todo (done|x) № задачи** - отметить задачу выполненной 
* **todo (del|-) № задачи** - удалить задачу
* **todo (change|*) № задачи Новый текст задачи** - изменить задачу
* **todo ** № задачи дополнительный текст задачи** - добавляет к задаче новый текст
* **todo step № задачи текст_шага** - добавление к задачи тега step:текущая_дата и текста_шага. Используется для отметок, когда и что было сделано по задаче.
* **todo clear** - очистить список задач
* **todo start|stop|finish № задачи** - изменение статуса задачи (finish не помечает задачу как выполненную). Так же добавляет тэг status_tag:Текущая дата
* **todo (backup|restory)** - сохранить/восстановить список задач 

#### Планируемое

* **todo **** TAG:STRING - изменяет/добавляет TAG 2019-02-05
* **todo list expired** - список просроченых задач 

##### Планируемое закрыто
* **todo list (+|@)** - список задач проекта/контекста  **Используем фильтр**
* **todo list (run|start|stop|finish)** - список задач с выбранным статусом **Используем фильтр**
* **todo run|start|stop|finish № задачи** - изменение статуса задачи с добавлением тэга и даты (finish должен так же помечать задачу как выполненную)
* **todo list week/day|month** - список задач на день/неделю/месяц ()
 
#### Автозамены:
##### (due|t):слово
* today:tommorow:week:month:next_week:next_month:Monday:tuesday:wednesday:thursday:friday:saturday:sunday
* сегодня:завтра:неделя:месяц:следующая_неделя:следующий_месяц:понедельник:вторник:среда:четверг:пятница:суббота:воскресенье

##### слово
* вчера:сегодня:завтра
* yesterday:today:tomorrow

#### Формат
x (A) 2011-03-05 2011-03-01 Описание задачи **+проект** __@контекст__ **due:**2011-03-03 **t:**2011-03-02 **status:**planed

##### Порядок важен
1. **x** - Означает, что задача выполнена
1. **(A-Z)** - Приоритет задачи. Используется для сортировки
1. **2011-03-05** - Дата выполнения
1. **2011-03-01** - Дата создания

##### Порядок не важен
* **due:2011-03-03** - Когда нужно выполнить
* **t:2011-03-02** - Когда нужно начать выполнять
* **rec:[0-9][dwmy]** - Повторять задачи через дни/недели/месяцы/года
* **status:planed** - статус задачи. Стандартные: planed, runing, stoped, finished (проконтролировать)
* **+проект** - название проекта без пробелов
* **@контекст** - название контекста без пробелов

update:2019-01-08
update:2019-02-03
update:2019-03-29

";

}

function help()
{
		return "### Команды действующие в любом чате
* **help** - текущая подсказка

#### Работа с историей 
**На работу todo не влияет. Он сохраняется**
* **history clear**  - очистить историю чата. Очищается вся история у всех присутствующих в чате. **Сохраняются закрепленные сообщения ** Чтобы увидеть результат требуется перегрузить чат (**Ctrl + R** или **F5**).
* **history clear old**  - то же самое, только удаляет сообщения старее 24 часов.
На сервере удаляются. **На мобильном клиенте остаются до очистки данных приложения :-(. [bug](https://github.com/RocketChat/Rocket.Chat.Android/issues/1353)**


* **todo** - управление задачами. Подробнее в разделе todo ниже
****
### Горячие клавиши
---
##### Шрифт
* **Ctrl + Shift + =** - Увеличить шрифт
* **Ctrl + -** - Уменьшить шрифт
* **Ctrl + 0** - Вернуть нормальный размер
---
##### Редактирование сообщений
* **Стрелочки вверх или вниз** - перейти к редактированию сообщания
* **Shift + Enter** - добавить новую строку в сообщение
* **Alt + вверх** или **вниз** - перейти к началу/концу сообщения
---
###### Управление чатами
* **Ctrl + R** - Обновить чат
* **Ctrl + ESC** или **Shift + ESC** - отметить все сообщения **во всех чатах** как прочитанные
* **Alt + влево** или **вправо** - переключиться между активными чатами/каналами
* **Ctrl + P** или **K** - перейти к поиску пользователя или канала

****
* **В чатах/комнатах/каналах поддерживается простое форматирование текста - [Markdown](https://ru.wikipedia.org/wiki/Markdown) ( [RocketChat MD](https://rocket.chat/docs/contributing/documentation/markdown-styleguide/) )**
***

".todo_help();

}


function todo_add($text)
{	
	$text=date('Y-m-d').' '.todo_format_pre($text);
	if (todo_get_id($text)!=false)
		return "**Task duplicate #:".todo_get_id($text)."**"."
".$text;

	todo_save($text);
//		return "Task id:".todo_get_id($text)." ".$text;

	if (todo_get_id($text)!=false)
		return "**id ".todo_get_id($text).":** ".$text;
	else
		return $text;
//	$WebHook->sendmessage("Добавлена задача:".$text);
}

$WebHook= new \RocketChat\WebHook();
$text=$WebHook->postData['text'];
$roomID=$WebHook->postData['channel_id'];
$file='/usr/data/script/todo/'.$roomID.'.txt';
#$get_messages=get_json();
#debug($WebHook,1);
$get_messages=$WebHook->postData;
$get_cmd=check_cmd($text);
$get_cmd_ = new TaskCmd($text);
if (count($WebHook->text)>1)
	$get_cmd_ = new TaskCmd($WebHook->text[0]);
debug($get_cmd_,1);
switch ($get_cmd)
{
	case "clear history":
		$api = new \RocketChat\Client();
		$admin = new \RocketChat\User($USER, $PASS);
		$admin->login();
		$channel = new \RocketChat\Channel($WebHook->postData['channel_name']);
		$channel->id=$WebHook->postData['channel_id'];
		if ($channel->clear_history())
			$WebHook->sendmessage("Complete. Need reload. (**Ctrl + R**)");
		else 
			$WebHook->sendmessage("Failed.");
		break;

        case "clear history old":
		$api = new \RocketChat\Client();
		$admin = new \RocketChat\User($USER, $PASS);
		$admin->login();
		$channel = new \RocketChat\Channel($WebHook->postData['channel_name']);
		$channel->id=$WebHook->postData['channel_id'];
		if ($channel->clear_history("-1"))
			$WebHook->sendmessage("Complete before 24h. Need reload. (**Ctrl + R**)");
		else 
			$WebHook->sendmessage("Failed.");
                break;

	case "help":
		$WebHook->sendmessage(help());
		break;
	case "todo":
		$WebHook->sendmessage(todo_help());
		break;
	case "todo +":
		//$TASK_TEXT=todo_add(substr($text,6));
		$TEXT_MESSAGE="";
		$task_comment_new="";
		$task_list= new Task_List($file);
		
		for ($i=0;$i<count($WebHook->text);$i++)
		{
			if ($i==0)
				{
					if ($WebHook->postData['channel_name']=='korshunov.tmp' || $WebHook->postData['channel_name']=='admin117.todo')
						$task_comment_new=$task_comment_new." @".$WebHook->postData['user_name'];

					$TaskNewList[]['orig']=trim($get_cmd_->getParam()." ".$task_comment_new);
					$FirstTask=new Task(todo_format_pre(trim($get_cmd_->getParam()." ".$task_comment_new)));
					$FirstTaskMetadata=trim("+".implode(" +",$FirstTask->projects)." @".implode(" @",$FirstTask->contexts));
					/*
					$FirstTaskMetadata=str_replace("+ ","",$FirstTaskMetadata);
					$FirstTaskMetadata=str_replace("@ ","",$FirstTaskMetadata);
					$FirstTaskMetadata=str_replace("  ","",$FirstTaskMetadata);
					*/
					$FirstTaskMetadata=preg_replace("/[+@ ] /","",$FirstTaskMetadata);
				}
			else
				$TaskNewList[]['orig']=trim($WebHook->text[$i]." ".$FirstTaskMetadata);


			$TaskNewList[count($TaskNewList)-1]['result']=$task_list->add(todo_format_pre($TaskNewList[count($TaskNewList)-1]['orig']));
		
			#$TaskNewList[][result]=$TASK_TEXT;
			#$TaskNewList[][result]=$TASK_TEXT;
			$task_list->save();
		}
		for ($i=0;$i<count($TaskNewList);$i++)
		{
			if ($task_list->getIdByText($TaskNewList[$i]['result'])!=false)
			{
				$task_id=$task_list->getIdByText($TaskNewList[$i]['result'])[0]+1;
				$TEXT_MESSAGE=$TEXT_MESSAGE."\n#### Добавлена задача #".$task_id."\n".$task_list->task[$task_id-1]->getRawTask();
			}
			else
				$TEXT_MESSAGE=$TEXT_MESSAGE."\n#### Ошибка добавления задачи:\nText:".$TaskNewList[$i]['orig']." - ".$TaskNewList[$i]['result'];
		}
		$WebHook->sendmessage($TEXT_MESSAGE);
		
		break;
	case "todo done":
                $TASK_TEXT=todo_done(substr($text,10)-1);
		$WebHook->sendmessage("##### Your todo list:\n".todo_format(todo_list(true,"today")));
                break;
	case "todo x":

		$task_list= new Task_List($file);
		$TaskCmd=new 	TaskCmd($text);
		$TODO_CUR_BLOCK="";
		$TEXT_MESSAGE="";
		$task_comment_new="time_x:".date('H:i:s');
		if ($WebHook->postData['channel_name']=='korshunov.tmp' || $WebHook->postData['channel_name']=='admin117.todo')
			$task_comment_new=$task_comment_new." @".$WebHook->postData['user_name'];
		if (strlen($TaskCmd->getParam())>0)
			$task_comment_new=$task_comment_new." ".$TaskCmd->getParam();
		
		$TASK_ID_LIST=$TaskCmd->getId();
		
		for ($i=0;$i<count($TASK_ID_LIST);$i++)
		{
			$TODO_ID=$TaskCmd->getId()[$i];
#			debug("TODO_ID:	".var_export($TODO_ID,true));
			$TODO_OLD=$task_list->task[$TODO_ID-1];
			if ($task_list->task[$TODO_ID-1]->recurrence()!==false)
			{
				$task_list->task[]=new Task($task_list->task[$TODO_ID-1]->recurrence());
			}
			$task_list->task[$TODO_ID-1]->setCompleted($task_comment_new);
			
			$TEXT_MESSAGE=$TEXT_MESSAGE."\n#### Task #".$TODO_ID." mark as done. \n**Text task:**".$TODO_OLD." ".$TODO_CUR_BLOCK."\n";
		}
		$task_list->save();
		$TODO_NEW_FILTER=date('Y-m-d');
#		$TEXT_MESSAGE=$TEXT_MESSAGE."\n#### Your todo list:\n".todo_format(todo_list(true,$TODO_NEW_FILTER));
		$TEXT_MESSAGE=$TEXT_MESSAGE."\n#### Your todo list:\n".todo_list(true,$TODO_NEW_FILTER);
#		$TEXT_MESSAGE="Complete";
		
#		debug("
#		Message2:".var_export($task_list,true));
		
		$WebHook->sendmessage($TEXT_MESSAGE);
#                $TASK_TEXT=todo_done(substr($text,6)-1);
#        	$WebHook->sendmessage("##### Task #".substr($text,6)." mark as done.\n **Text task:**".$TASK_TEXT." \n**Your todo list:**\n".todo_format(todo_list(true,"today")));
                break;
	case "todo *":
		$TODO_NEW=trim(substr($text,6));
		$TODO_NEW_FILTER=trim(substr($TODO_NEW,strpos($TODO_NEW,' ')))."|today";
		$TODO_ID=substr($TODO_NEW,0,strpos($TODO_NEW,' '));
		$TODO_OLD=todo_get_text($TODO_NEW);
                todo_change($TODO_NEW);
#		$TODO_NEW=todo_get_text($TODO_ID);
                $WebHook->sendmessage("Task #".$TODO_ID." changed. \n**Old:**".$TODO_OLD."\n**New:**".$TODO_NEW."\nYour todo list:\n".todo_format(todo_list(false,$TODO_NEW_FILTER)));
                break;
	case "todo **":
		$TODO_NEW=trim(substr($text,7));
		$TODO_NEW_FILTER=trim(substr($TODO_NEW,strpos($TODO_NEW,' ')))."|today";
		$TODO_ID=substr($TODO_NEW,0,strpos($TODO_NEW,' '));
		$TODO_OLD=todo_get_text($TODO_NEW);
                todo_change($TODO_ID." ".trim($TODO_OLD)." ".trim(substr($TODO_NEW,strpos($TODO_NEW,' '))));
#		$TODO_NEW=todo_get_text($TODO_ID);
                $WebHook->sendmessage("Task #".$TODO_ID." changed. \n**Old:**".$TODO_OLD."\n**New:**".trim($TODO_OLD)." ".$TODO_NEW."\nYour todo list:\n".todo_format(todo_list(false,$TODO_NEW_FILTER)));
                break;
	
	case "todo change":
                todo_change(substr($text,11));
                $WebHook->sendmessage("Task #".substr($text,11)." changed. \nYour todo list:\n".todo_format(todo_list()));
                break;
	case "todo step":
		$TaskCmd=new TaskCmd($text);
		$TEXT_MESSAGE="";
		#debug("Get_cmd:	".var_export($TaskCmd,true));
		$TODO_CUR_BLOCK="step:".date('Y-m-d')." ".$TaskCmd->getParam();
		
		debug("       ************ ");
		debug("
		TODO_CUR_BLOCK:	".var_export($TODO_CUR_BLOCK,true));
		$TASK_ID_LIST=$TaskCmd->getId();
		for ($i=0;$i<count($TASK_ID_LIST);$i++)
		{
			$TODO_ID=$TaskCmd->getId()[$i];
			$TODO_OLD=todo_get_text($TODO_ID);
			debug("
			TODO_OLD:	".var_export($TODO_OLD,true));
	
			todo_change($TODO_ID." ".trim($TODO_OLD)." ".$TODO_CUR_BLOCK);
			$TEXT_MESSAGE=$TEXT_MESSAGE."\n#### Task #".$TODO_ID." changed. \n**Old:**".$TODO_OLD."\n**New:**".trim($TODO_OLD)." ".$TODO_CUR_BLOCK."";
			debug("TEXT_MESSAGE:	".var_export($TEXT_MESSAGE,true));
		}
		$TODO_NEW_FILTER="step:".date('Y-m-d');
		$TEXT_MESSAGE=$TEXT_MESSAGE."\n#### Your todo list:\n".todo_format(todo_list(false,$TODO_NEW_FILTER));
		$WebHook->sendmessage($TEXT_MESSAGE);

/*		$TODO_NEW=trim(substr($text,9));
		$TODO_ID=substr($TODO_NEW,0,strpos($TODO_NEW,' '));
		$TODO_OLD=todo_get_text($TODO_NEW);
		$TODO_CUR_BLOCK="step:".date('Y-m-d')." ".trim(substr($TODO_NEW,strpos($TODO_NEW,' ')));
		$TODO_NEW_FILTER="step:".date('Y-m-d');

#		todo_change($TODO_ID." ".trim($TODO_OLD)." step:cur_data".trim(substr($TODO_NEW,strpos($TODO_NEW,' '))));
		todo_change($TODO_ID." ".trim($TODO_OLD)." ".$TODO_CUR_BLOCK);
#		$WebHook->sendmessage("Task #".$TODO_ID." changed. \n**Old:**".$TODO_OLD."\n**New:**".trim($TODO_OLD)." ".$TODO_CUR_BLOCK."\nYour todo list:\n".todo_format(todo_list(false,"today")));
		$WebHook->sendmessage("Task #".$TODO_ID." changed. \n**Old:**".$TODO_OLD."\n**New:**".trim($TODO_OLD)." ".$TODO_CUR_BLOCK."\nYour todo list:\n".todo_format(todo_list(false,$TODO_NEW_FILTER)));
*/
                break;

	case "todo start":
                todo_status_change(substr($text,10),"start",$get_messages['user_name']);
        	$WebHook->sendmessage("Task #".substr($text,10)." status changed to start. \nYour todo list:\n".todo_format(todo_list(false,"today")));
                break;

	case "todo stop":
                todo_status_change(substr($text,9),"stop",$get_messages['user_name']);
        	$WebHook->sendmessage("Task #".substr($text,9)." status changed to stop. \nYour todo list:\n".todo_format(todo_list(false,"today")));
                break;

	case "todo finish":
                todo_status_change(substr($text,11),"finish",$get_messages['user_name']);
                $WebHook->sendmessage("Task #".substr($text,11)." status changed to finish. \nYour todo list:\n".todo_format(todo_list(false,"today")));
                break;


        case "todo del":
		$TASK_ID=substr($text,8)-1;
		$TASK_ID_USER=$TASK_ID+1;
                $TASK_TEXT=todo_del($TASK_ID);
                $WebHook->sendmessage("Task #".$TASK_ID_USER." del. **Text**: ".$TASK_TEXT."\nYour todo list:\n".todo_format(todo_list(false,"today")));
                break;
	case "todo -":
		$TASK_ID=substr($text,6)-1;
		$TASK_ID_USER=$TASK_ID+1;
                $TASK_TEXT=todo_del($TASK_ID);
                $WebHook->sendmessage("Task #".$TASK_ID_USER." del. **Text**: ".$TASK_TEXT."\nYour todo list:\n".todo_format(todo_list(false,"today")));
                break;
	case "todo list":
#	debug($text,1);
		$filter=substr($text,10);
#		debug($filter,1);
		$WebHook->sendmessage("**Your todo list:**
".todo_list(false,$filter));
#".todo_format(todo_list(false,$filter)));
		break;
	case "todo list all":
		$filter=substr($text,14);
		$WebHook->sendmessage("**Your todo list:**
".todo_list(true,$filter));
#".todo_format(todo_list(true,$filter)));
		break;
	case "todo clear":
		todo_clear();
                $WebHook->sendmessage("Your todo cleared.");
                break;
	case "cron_add":
		#$roomID=get_cur_roomid(get_json());
		$roomID=$WebHook->postData['channel_id'];
		cron_add($roomID);
        	$WebHook->sendmessage("Added.");
                break;
	case "todo backup":
		todo_backup();
        	$WebHook->sendmessage("Your todo list backuped.");
                break;
	case "todo restore":
		todo_restore();
        	$WebHook->sendmessage("Your todo list restrored from last backup.");
                break;

	case "destroy room":
		$roomID=$WebHook->postData['channel_id'];
		destroy_room($AuthToken,$roomID);

	case "history_autoclear_save":
		$get_messages=get_json();
		$USER_ID=$get_messages['user_id'];
		$USER_NAME=$get_messages['user_name'];
		$CHANNEL_ID=$get_messages['channel_id'];
                history_autoclear_save($USER_ID,$USER_ID.substr($text,19));

	default:
//		$WebHook->sendmessage("Your message1: ");
#		$WebHook->sendmessage("Your message: ".get_message(get_json()));
//		$WebHook->sendmessage(get_message());
}

exit;
if (strpos($get_cmd,"help")===0) 
{
	$WebHook->sendmessage("В любом чате можно использовать некоторые команды:

* **help** - текущая подсказка
* **clear history**  - очистить историю чата. Очищается вся история у всех присутствующих в чате.");
}
else
{
#	$WebHook->sendmessage("Pos: ".strpos($get_cmd,"qq"));
}


?>

