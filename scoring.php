<?php

class Task_List {
	public $file;
	protected  $filter="";
	public $task;
	
	public function __construct($file="",$full=false,$filter="") {
		
		$this->file=$file;
		$this->filter=$filter;
		if (!empty($file))
		$this->load($full,$this->filter);
	}
	
	public function count($type="open"){
		$count=0;
		if ($type=="all")
			$count=count($this->task);
		if ($type=="notCompleted"||$type=="open")
			for ($i=0;$i<count($this->task);$i++)
				if (!$this->task[$i]->isCompleted())
					$count++;
		if ($type=="Completed")
			for ($i=0;$i<count($this->task);$i++)
				if ($this->task[$i]->isCompleted())
					$count++;
		return $count;
	}
	
	function load($full=false,$filter=""){
	$file_array = file($this->file);
	for($i=0;$i<count($file_array);$i++)
	{
		if (($full==false) && (strpos($file_array[$i],"x ")===0))
			continue;
		if ($this->filter!="")
			if (preg_match('/'.$this->filter.'/',$file_array[$i]) == 0)
				continue;
		$this->task[]=new Task($file_array[$i]);
	}
	}
}

class Task
{
    /* @var string The task as passed to the constructor. */
    protected $rawTask;
    /* @var string The task, sans priority, completion marker/date. */
    protected $task;
    
    /* @var boolean Whether the task has been completed. */
    protected $completed = false;
    /* @var DateTime The date the task was completed. */
    protected $completionDate;
    
    /* @var string A single-character, uppercase priority, if found. */
    protected $priority;
    /* @var DateTime The date the task was created. */
    protected $created;
    
    /* @var array A list of project names found (case-sensitive). */
    public $projects = array();
    /* @var array A list of context names found (case-sensitive). */
    public $contexts = array();
    /*
     * @var array A map of meta-data, contained in the task.
     * @see __get
     * @see __set
     */
    protected $metadata = array();
    /**
     * Create a new task from a raw line held in a todo.txt file.
     * @param string $task A raw task line
     * @throws EmptyString When $task is an empty string (or whitespace)
     */
    public function __construct($task) {
        $task = trim($task);
        if (strlen($task) == 0) {
            throw new Exception\EmptyString;
        }
        $this->rawTask = $task;
        
        // Since each of these parts can occur sequentially and only at
        // the start of the string, pass the remainder of the task on.
        $result = $this->findCompleted($task);
        $result = $this->findPriority($result);
        $result = $this->findCreated($result);
        
        /*$result = trim($result);
        if (strlen($result) == 0) {
            //throw new Exception\EmptyString;
            return null;
        }*/
        $this->task = $result;
        
        // Find metadata held in the rest of the task
        $this->findContexts($result);
        $this->findProjects($result);
        $this->findMetadata($result);
    }
    
    /**
     * Returns the age of the task if the task has a creation date.
     * @param DateTime|string $endDate The end-date to use if the task
     * does not have a completion date. If this is null and the task
     * doesn't have a completion date the current date will be used.
     * @return DateInterval The age of the task.
     * @throws CannotCalculateAge If the task does not have a creation date.
     */
    public function age($endDate = null) {
		/* Возраст мерием:
		1. Если задан due: то от него
		2. Если задан t: то от него
		3. Иначе от даты создания
		*/
        if (!isset($this->created)) {
            throw new Exception\CannotCalculateAge;
        }
     debug("1");   
        // Decide on an end-date to use - completionDate, then a
        // provided date, then the current date.
#        $end = new \DateTime("now");
# Если берем Now, то учитывается и время, поэтому получается меньше дня.
        $end = new \DateTime(date("Y-m-d"));
        
        if (isset($this->completionDate)) {
            $end = $this->completionDate;
        } else if (!is_null($endDate)) {
            if (!($endDate instanceof \DateTime)) {
                $endDate = new \DateTime($endDate);
            }
            $end = $endDate;
        }
        // Проверяем, задан ли due
#        debug("1"); 
		if ($this->__isset("due"))
		{
#			debug("DUE: ".var_export($this,true));
			$deadline = new \DateTime($this->__get("due"));
		}
		elseif ($this->__isset("t"))
		{
#			debug("T: ".var_export($this,true));
			$deadline = new \DateTime($this->__get("t"));
		}
			else
			{
				$deadline=$this->getCreationDate();
#				debug(var_export($this,true));
			}
#			debug("
#			dedline:".var_export($deadline,true)." end: ".var_export($end,true));
        $diff = $deadline->diff($end);
#			debug("DIFF".var_export($diff,true));
#		echo "
#		diff:".var_export($diff,true)." end: ".var_export($end,true)."dedline:".var_export($deadline,true);
        if ($diff->invert) {
#			debug("DIFF2:   ".var_export($diff,true));

					$diff = $end->diff($deadline);
			$age=-$diff->days;
//            throw new Exception\CompletionParadox;
        }
		else $age=$diff->days;
#		debug(" age: ".$age);
        return $age;
    }
    
    /**
     * Add an array of projects to the list.
     * Using this method will prevent duplication in the array.
     * @param array $projects Array of project names.
     */
    public function addProjects(array $projects) {
        $projects = array_map("trim", $projects);
        $this->projects = array_unique(array_merge($this->projects, $projects));
    }
    
    /**
     * Add an array of contexts to the list.
     * Using this method will prevent duplication in the array.
     * @param array $contexts Array of context names.
     */
    public function addContexts(array $contexts) {
        $contexts = array_map("trim", $contexts);
        $this->contexts = array_unique(array_merge($this->contexts, $contexts));
    }
    
    /**
     * Access meta-properties, as held by key:value metadata in the task.
     * @param string $name The name of the meta-property.
     * @return string|null Value if property found, or null.
     */
    public function __get($name) {
        return isset($this->metadata[$name]) ? $this->metadata[$name] : null;
    }
    
    /**
     * Check for existence of a meta-property.
     * @param string $name The name of the meta-property.
     * @return boolean Whether the property is contained in the task.
     */
    public function __isset($name) {
        return isset($this->metadata[$name]);
    }
    
    /**
     * Re-build the task string.
     * @return string The task as a todo.txt line.
     */
    public function __toString() {
        $task = "";
        if ($this->completed) {
            $task .= sprintf("x %s ", $this->completionDate->format("Y-m-d"));
        }
        
        if (isset($this->priority)) {
            $task .= sprintf("(%s) ", strtoupper($this->priority));
        }
        
        if (isset($this->created)) {
            $task .= sprintf("%s ", $this->created->format("Y-m-d"));
        }
        
        $task .= $this->task;
        return $task;
    }
    
    public function isCompleted() {
        return $this->completed;
    }
    
    public function getCompletionDate() {
        return $this->isCompleted() && isset($this->completionDate) ? $this->completionDate : null;
    }
    
    public function getCreationDate() {
        return isset($this->created) ? $this->created : null;
    }
    
    /**
     * Get the remainder of the task (sans completed marker, creation
     * date and priority).
     */
    public function getTask() {
        return $this->task;
    }
    
    public function getRawTask() {
        return $this->rawTask;
    }
    
    public function getPriority() {
        return $this->priority;
    }
    
    /**
     * Looks for a "x " marker, followed by a date.
     *
     * Complete tasks start with an X (case-insensitive), followed by a
     * space. The date of completion follows this (required).
     * Dates are formatted like YYYY-MM-DD.
     *
     * @param string $input String to check for completion.
     * @return string Returns the rest of the task, without this part.
     */
    protected function findCompleted($input) {
        // Match a lower or uppercase X, followed by a space and a
        // YYYY-MM-DD formatted date, followed by another space.
        // Invalid dates can be caught but checked after.
        $pattern = "/^(X|x) (\d{4}-\d{2}-\d{2}) /";
        if (preg_match($pattern, $input, $matches) == 1) {
            // Rather than throwing exceptions around, silently bypass this
            try {
                $this->completionDate = new \DateTime($matches[2]);
            } catch (\Exception $e) {
                return $input;
            }
            
            $this->completed = true;
            return substr($input, strlen($matches[0]));
        }
        return $input;
    }
    
    /**
     * Find a priority marker.
     * Priorities are signified by an uppercase letter in parentheses.
     *
     * @param string $input Input string to check.
     * @return string Returns the rest of the task, without this part.
     */
    protected function findPriority($input) {
        // Match one uppercase letter in brackers, followed by a space.
        $pattern = "/^\(([A-Z])\) /";
        if (preg_match($pattern, $input, $matches) == 1) {
            $this->priority = $matches[1];
            return substr($input, strlen($matches[0]));
        }
        return $input;
    }
    
    /**
     * Find a creation date (after a priority marker).
     * @param string $input Input string to check.
     * @return string Returns the rest of the task, without this part.
     */
    protected function findCreated($input) {
        // Match a YYYY-MM-DD formatted date, followed by a space.
        // Invalid dates can be caught but checked after.
        $pattern = "/^(\d{4}-\d{2}-\d{2}) /";
        if (preg_match($pattern, $input, $matches) == 1) {
            // Rather than throwing exceptions around, silently bypass this
            try {
                $this->created = new \DateTime($matches[1]);
            } catch (\Exception $e) {
                return $input;
            }
            return substr($input, strlen($matches[0]));
        }
        return $input;
    }
    
    /**
     * Find @contexts within the task
     * @param string $input Input string to check
     */
    protected function findContexts($input) {
        // Match an at-sign, any non-whitespace character, ending with
        // an alphanumeric or underscore, followed either by the end of
        // the string or by whitespace.
##        $pattern = "/@(\S+\w)(?=\s|$)/";
        $pattern = "/@(\S+)(?=\s|$)/";
        if (preg_match_all($pattern, $input, $matches) > 0) {
            $this->addContexts($matches[1]);
        }
    }
    
    /**
     * Find +projects within the task
     * @param string $input Input string to check
     */
    protected function findProjects($input) {
        // The same rules as contexts, except projects use a plus.
##        $pattern = "/\+(\S+\w)(?=\s|$)/";
        $pattern = "/\+(\S+)(?=\s|$)/";

        if (preg_match_all($pattern, $input, $matches) > 0) {
            $this->addProjects($matches[1]);
        }
    }
    
    /**
     * Metadata can be held in the string in the format key:value.
     * This is usually used by add-ons, which provide their own
     * formatting rules for tasks.
     * This data can be accessed using __get() and __isset().
     *
     * @param string $input Input string to check
     * @see __get
     * @see __set
     */
    protected function findMetadata($input) {
        // Match a word (alphanumeric+underscores), a colon, followed by
        // any non-whitespace character.
        $pattern = "/(?<=\s|^)(\w+):(\S+)(?=\s|$)/";
        if (preg_match_all($pattern, $input, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
		if ($match[1]=="step") 
	                $this->metadata[$match[1]][] = $match[2];
		else	
	                $this->metadata[$match[1]] = $match[2];
            }
        }
    }
    public function getScore(){
			$score=5;
	$score+=count($this->projects);
	$score+=count($this->contexts);
	if ($this->__isset("due")) $score++;
	if ($this->__isset("t")) $score++;

	if ($this->__isset("step"))
        {
		$score+=count($this->__get("step"));
}





	if ($this->getPriority()!=NULL) 
		{
			$prior2alf=26-(ord(strtoupper($this->getPriority())) - ord('A'));
			$score+=$prior2alf;
		}
	if ($this->isCompleted()) $score=$score+10;
	return $score;
		}
}


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

			
		debug("project score sum1: ".var_export($_score,true));
			
		}
		debug("project score sum2: ".var_export($_score,true));
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
