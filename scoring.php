<?php

include_once 'task.php';

class Score {
	/*
	 * Create task 5
	 * due
	*/
	public function getScoreTask($task){
		/*
		 * Мысли по поводу бонусов за действия
		 * 1. Бонусы делятся на: Деньги, Опыт, Жизнь
		 * 2. Деньги даются за все действия
		 * 3. Опыт дается за закрытые задачи
		 * 4. Жизнь отнимается за просроченные задачи 
		 * 
		 * Опыт распределяется по проектам - на данный момент, каждому проекту поровну.
		 * Опыт распределяется по контекстам - на данный момент, каждому контексту поровну.
		*/
		# Таблица бонусов разные действия
		$score_table['create']=5; # Создание новой задачи.
		$score_table['due']=1; # Указание срока выполения.
		$score_table['t']=1; # Указание даты планирования. Т.е. когда нужно вспомнить о вопросе.
		$score_table['project']=1; # Описание одного проекта
		$score_table['contexts']=1; # Описание одного контекста
		$score_table['step']=1; # За каждый шаг
		$score_table['complete']=10; # Выполнение задачи.
		$score_table['age0']=5; # Выполнение в срок
		$score_table['age+']=-1; # Штраф за просрочку
		$score_table['age-']=5; # Награда за досрочное выполнение
		$score_table['priority']=5; # Продумать зависимость награды от приоритета.

		$score=0;
		$score+=$score_table['create'];
		$score+=count($task->projects)*$score_table['project'];
		$score+=count($task->contexts)*$score_table['contexts'];

		debug("score_age start:".$score."  ");
		$age=0;
#		debug("
#		".var_export($task,true));
		if ($task->isCompleted())
		{
			$score+=$score_table['complete'];
		$age=$task->age();
		if ($age==0)
			$score+=$score_table['age0'];
		elseif ($age>0)
			$score+=(1+$age)*$score_table['age+'];
			elseif ($age<0)
			$score+=(-$age)*$score_table['age-'];

		debug("score_age stop:".$score." age:".$age."
		");
			
#	echo "
#	Возраст".$task->getRawTask().":".var_export($task->age(),true)."
#	";
	}
		if ($task->__isset("due")) $score+=$score_table['due'];
		if ($task->__isset("t")) $score+=$score_table['t'];
		if ($task->__isset("step"))
		{
			$score+=count($task->__get("step"))*$score_table['step'];
		}
		if ($task->getPriority()!=NULL) 
		{
			$prior2alf=26-(ord(strtoupper($task->getPriority())) - ord('A'));
			$score+=$prior2alf;
		}
		
		$_score['total']=$score;
		debug("task: ".var_export($task,true));
		for ($i=0;$i<count($task->projects);$i++)
		{
			$_score['projects'][$task->projects[$i]]=$score/count($task->projects);
		}
		for ($i=0;$i<count($task->contexts);$i++)
		{
			$_score['contexts'][$task->contexts[$i]]=$score/count($task->contexts);
		}

		debug("project score: ".var_export($_score,true));
		return $_score;
	}
	
	public function getScoreTaskList($taskList){
		$score=0;
		$_score['total']=0;
		$_score['projects']="";
		$_score['contexts']="";
		for($i=0;$i<count($taskList);$i++)
		{
			$_score_cur=$this->getScoreTask($taskList[$i]);
			$_score['projects']=$this->array_add($_score['projects'],$_score_cur['projects']);
			$_score['contexts']=$this->array_add($_score['contexts'],$_score_cur['contexts']);
			
			$score_cur=$_score_cur[total];
			$_score['total']+=$_score_cur[total];
			$score+=$score_cur;

			
#		debug("project score sum1: ".var_export($_score,true));
			
		}
#		debug("project score sum2: ".var_export($_score,true));
#		return $score;
		return $_score;
#		return $_score['total'];
	}
/*  # Функция по сохранению результатов в файл
  	public function save2file($taskList,$filename){
		
	}
*/
	protected function array_add($a1, $a2) {  // ...
  // adds the values at identical keys together
		$aRes = $a1;
#  debug("array_add 1 : ".var_export($aRes,true));
#  debug("array_add 2 : ".var_export($a2,true));
		
		if (empty($a1)) return  $a2;
		if (empty($a2)) return  $a1;

		foreach (array_slice(func_get_args(), 1) as $aRay) {
			foreach (array_intersect_key($aRay, $aRes) as $key => $val) $aRes[$key] += $val;
				$aRes += $aRay; }
#		ksort($aRes); // Отсортировать по ключу
		arsort($aRes); // Отсортировать по значению по убыванию

		return $aRes; }
}

function scoring_tasks($task_list)
{
$score=0;
for($i=0;$i<count($task_list);$i++)
	{
		$score_cur=$task_list[$i]->getScore();
		$score+=$score_cur;
	echo "
".($i+1).". ".$score_cur." - ".$task_list[$i]->getRawTask();

}
return $score;
}
/*
$roomID="8ury3B9dtdZKgfvqq";  //Ged
#$roomID="EtP4MMXZ8WHWEMw6e";  //Test

$file='/usr/data/script/todo/'.$roomID.'.txt';
#$task_list= new Task_List($file,true,date('Y-m-d', strtotime('-0 days')));
$task_list= new Task_List($file,true);
$score_count= new Score;
#echo var_export($task_list,true);

$score= $score_count->getScoreTaskList($task_list->task);



#echo var_export($task_list,true);

#$score=scoring_tasks($task_list->task);
echo "
Total: x".$task_list->count("Completed")."/+".$task_list->count('all')."/".$task_list->count("notCompleted")." Score: ".$score."
";
*/
?>
