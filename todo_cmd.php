<?php
/*
		include_once 'rpglife/scoring.php';
		include_once 'rpglife/task.php';
		include_once 'rpglife/todo_cmd.php';
		include_once 'robot.cfg';
*/
class TaskCmd
{
    /* @var string The task as passed to the constructor. */
    protected $rawCmd;
    /* @var string The task, sans priority, completion marker/date. */
    protected $cmd;
    protected $cmdRaw;
    protected $id;
    protected $rawId;

    protected $param="";
    // Определяем команды с которыми передаются ID.
    protected $ListcmdNeedID=array("**","*","start","stop","step","finish","-","x","done");
    protected $ListcmdOnlyOneID=array("*");
    protected $correct=false;
    
    
    public function __construct($text) {
        $this->rawCmd = trim($text);
        if (strlen($text) == 0) {
            return;
        }
        $this->getCmdFromText($text);
	$this->check_cmd();

        if (in_array(explode(" ",$this->cmd)[1],$this->ListcmdNeedID))
					$this->getIDFromText($text);
        $this->getParamFromText();
        $this->checkError();
        
        /*
        $cmd_array = explode(" ", $cmd);
        for ($i=0;$i<count($cmd_array);$i++)
					$this->cmd=$cmd_array[0]." ".$cmd_array[1];
        for ($i=2;$i<=count($cmd_array);$i++)
					$this->param=trim($this->param." ".$cmd_array[$i]);
			*/

        //if (strpos($text,"todo done")===0)
       
        
        //$this->rawCmd = $cmd;
    }
/*    
    protected getIDfromRaw($cmd){
			
		}
*/   
		protected function check_cmd() {
			$cmd_list=explode(" ",$this->cmd);
			if ($cmd_list[0]=="todo")
			{
			        $this->cmdRaw=$cmd_list[0]." ".$cmd_list[1];

				switch ($cmd_list[1])
				{
					case "+":	$this->cmd=$cmd_list[0]." add";	break;
					case "add":	$this->cmd=$cmd_list[0]." add";	break;
					case "*":	$this->cmd=$cmd_list[0]." change";	break;
					case "change":	$this->cmd=$cmd_list[0]." change";	break;
					case "x":	$this->cmd=$cmd_list[0]." done";	break;
					case "done":	$this->cmd=$cmd_list[0]." done";	break;
					case "-":
					case "del":
					case "rm":	$this->cmd=$cmd_list[0]." del";	break;

					case "step":	$this->cmd=$cmd_list[0]." step";	break;
					case "clear":	$this->cmd=$cmd_list[0]." clear";	break;

					case "start":
					case "stop":
					case "finish":	$this->cmd=$cmd_list[0]." test";	break;

					
				}
{
			}
			
		}
	}
		protected function checkError(){
			$status=true;
			if (count($this->id)>1)
				if (in_array(explode(" ",$this->cmd)[1],$this->ListcmdOnlyOneID))
					$status=false;
					
					$this->correct=$status;
			return $status;
}
    protected function getCmdFromText($text) {
        $text=strtolower($text);
        
        $pattern = "/^([a-z]+) ([a-z-+*]+)/";
        if (preg_match($pattern, $text, $matches) == 1) {
            // Rather than throwing exceptions around, silently bypass this
            try {
                $this->cmd = trim($matches[0]);
            } catch (\Exception $e) {
                return $input;
            }
        };
    }
    protected function getIDFromText($text) {
#	echo "getIDFromText: ".var_export($text,true);
				$text=trim(substr($text,strlen($this->cmdRaw)));
#	echo "\ngetIDFromText2: ".var_export($text,true);
				
        $pattern = "/^([0-9,]+)/";
#        $pattern = "/^([0-9,-]+)/";
        
        if (preg_match($pattern, $text, $matches) == 1) {
            // Rather than throwing exceptions around, silently bypass this
            try {
							$this->rawId=trim($matches[0]);
							$this->id=array_unique(explode(",", trim($matches[0])));
							rsort($this->id);
            } catch (\Exception $e) {
                return $input;
            }
        };
    }
    protected function getParamFromText() {
	$this->param = trim(substr($this->rawCmd,strlen($this->cmdRaw." ".$this->rawId)));
    }
		
    public function getCmd() {
        return $this->cmd;
    }
    public function getParam() {
        return $this->param;
    }
    public function getId() {
        return $this->id;
    }
}
/*
//$test[]= new 	TaskCmd("todo start 15,16,17 description");
#$test[]= new 	TodoCmd("todo + 11,12,10 13 14 15 16 descripTion 90 test");
$test[]= new 	TaskCmd("todo x 11,12,10 13 14 15 16 descripTion 90 test");
#$test[]= new 	TodoCmd("");


echo "
".$test[0]->getCmd()."
".var_export($test,true)."
";

*/
?>
