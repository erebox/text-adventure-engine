<?php

namespace Erebox\TextAdventureEngine;

class Engine
{
    protected $game = null;

    //setup
    protected $game_room = null;
    protected $game_item = null;
    protected $game_trig = null;
    protected $game_conf = null;

    protected $parser_igno = [];
    protected $parser_verb = [];
    protected $parser_dire = [];
    protected $parser_item = [];

    protected $end = 0;    
    protected $player_pos = "";
    protected $inventory_max = 10;

    protected $inventory = [];
    protected $var = [];

    protected $resp_message = [];
    protected $resp_status = [];




    protected $curr_verb = "";
    protected $curr_item = "";

    protected $trigger_action = "";
    protected $trigger_item = "";

    //Tag
    protected $T_ROOM   = 'room';
    protected $T_ITEM   = 'item';
    protected $T_TRIG   = "trigger";
    protected $T_CONF   = "config";

    protected $T_ACTI   = "action";
    protected $T_IGNO   = "ignore";
    protected $T_DIRE   = "direction";

    protected $T_DESC   = "description";

    protected $T_POSI   = "position";

    protected $T_OPEN   = "openable";    //capacity
    protected $T_PICK   = "pickable";
    protected $T_REMO   = "removable";
    protected $T_EAT    = "eatable";
    //Flag
    protected $F_LOCA   = "@";
    protected $F_INVE   = "#";
    protected $F_REMO   = "-";
    protected $F_EAT    = "^";
    protected $F_ACTI   = ":";
    //Command
    protected $C_OPEN   = "open";
    protected $C_CLOS   = "close";
    protected $C_PICK   = "pickup";
    protected $C_LEAV   = "leave";
    protected $C_REMO   = "remove";
    protected $C_EAT    = "eat"; //ok
    protected $C_USE    = "use"; //ok
    protected $C_SPEA   = "speak";
    protected $C_INVE   = "inventory";
    protected $C_LOOK   = "look";
    protected $C_GOTO   = "goto";
    protected $C_EXIT   = "exit";
    protected $C_HELP   = "help";
    //Version
    protected $V_VERS   = "1.0.0";
    
    public function __construct($jsongame = null)
    {
        $this->load($jsongame);
    }

    public function load($jsongame = null) 
    {
        if ($jsongame) {
            $this->game = json_decode($jsongame, true);
        }
        $this->restart();
    }

    public function restart() 
    {
        if ($this->game && count($this->game) > 0) {
            $this->setup();
            $this->intro();
            $this->go();
        }
    }

    public function get() 
    {
        return [
            "message" => $this->resp_message, 
            "status" => $this->resp_status
        ];
    }

    public function parse($sentence)
    {
        $this->resp_message = [];
        $this->resp_status = [];
        if ($this->game && count($this->game)>0) {
            $this->parseSentence($sentence);
        }
    }

    public function ended() {
        return ($this->end == 1);
    }

    public function version() 
    {
        return $this->V_VERS;
    }

    public function debug($w = null) 
    {
        $r = $this->game;
        //$r["config"] = $this->game_conf;
        return json_encode($r, JSON_PRETTY_PRINT);
    }

    /**
     *  Private
     */
    
    private function setup()
    {
        // manage main elements: room item trigger config
        $this->game_room = (isset($this->game[$this->T_ROOM])) ? $this->game[$this->T_ROOM] : [];
        $this->game_item = (isset($this->game[$this->T_ITEM])) ? $this->game[$this->T_ITEM] : [];
        $this->game_trig = (isset($this->game[$this->T_TRIG])) ? $this->game[$this->T_TRIG] : [];
        $lang = "en";
        if (isset($this->game["lang"])) {
            $lang = $this->game["lang"];
        }
        $this->game_conf = (new DefaultConfig($lang))->get();
        $current_config = (isset($this->game[$this->T_CONF])) ? $this->game[$this->T_CONF] : [];
        foreach ($current_config as $cfg_key => $cfg_val) {
            $this->game_conf[$cfg_key] = $cfg_val;
        }

        if (isset($this->game_conf["ignore"])) {
            $this->parser_igno = array_flip($this->game_conf["ignore"]);
        }
        $this->parser_dire = $this->getTokenArray($this->game_conf[$this->T_DIRE]);
        $this->parser_verb = $this->getOnlyAlias($this->game_conf[$this->T_ACTI]);
        $this->parser_item = $this->getTokenAlias($this->game_item);

        $this->end = 0;
        $this->player_pos = $this->game_conf["init"];
        if (isset($this->game_conf["inventory_max"])) {
            $this->inventory_max = $this->game_conf["inventory_max"];            
        }
        
        $this->inventory = [];
        $this->var = [];
        if (isset($this->game_conf["variable"])) {
            $var2set = $this->game_conf["variable"];
            foreach ( $var2set as $curr_var => $curr_val) {
                $this->var[$curr_var] = $curr_val;
            }
        }

        $this->resp_message = [];
        $this->resp_status = [];
    }

    private function intro()
    {
        $this->arrStr2respMsg($this->game_conf["intro"]);
    }

    private function go()
    {
        $this->callTrigger();
        if ($this->end == 0) {
            $dove = $this->game_room[$this->player_pos];
            $txt = $this->txtAction("*", "where").$dove[$this->T_DESC].".\n";
            foreach ($this->game_item as  $k =>$v) {
                if ($v[$this->T_POSI] == $this->player_pos) {
                    $txt .= $this->txtAction("*", "see").$v[$this->T_DESC].".\n";
                }
            }
            $dom = $this->txtAction("*", "what");
            $this->resp_status = [ $txt, $dom];
        } else {
            $this->resp_status = [];
        }
    }    

    private function parseSentence($text)
    {
        $t1 = preg_replace('/\s+/', ' ',$text);
        if (strlen($t1)>0) {
            $t2 = explode(" ",$t1);
            if (count($t2)>0) {
                $verb = "";
                $item = "";
                $t3 = [];
                if (count($t2)>1) {
                    foreach ($t2 as $tcurr) {
                        if (!isset($this->parser_igno[$tcurr])) {
                            $t3[] = $tcurr;
                        }
                    }    
                } else {
                    $t3 = $t2;
                }
                $verb = $t3[0];
                if (!isset($this->parser_verb[$verb]) && !isset($this->parser_dire[$verb])) {
                    $this->resp_message[] = $this->txtAction("*", "parser_noverb")." '".$verb."'.";
                    $this->go();
                    return;
                }
                if (isset($t3[1])) {
                    $item = $t3[1];
                    if (!isset($this->parser_item[$item]) && !isset($this->parser_dire[$item])) {
                        $this->resp_message[] = $this->txtAction("*", "parser_noitem")." '".$item."'.";
                        $this->go();                            
                        return;
                    }
                }
                $this->command($verb, $item);
                $this->go();
            }
        } else {
            $this->resp_message[] = $this->txtAction("*", "parser_say");
            $this->go();
        }
        //return $this->end();
    }

    /**
     * Engine Command
     */

    private function command($verb, $item) {
        $this->curr_verb = "";
        $this->curr_item = "";
        if (isset($this->parser_verb[$verb])) {
            $this->curr_verb = $this->parser_verb[$verb];
            $this->curr_item = $item;
            $this->trigger_action = $this->curr_verb;
            $this->trigger_item = $this->curr_item;
            
            switch ($this->curr_verb) {
                case $this->C_OPEN: //ok
                    $this->cmdOpen($item);
                    break;
                case $this->C_CLOS: //ok
                    $this->cmdClose($item);
                    break;
                case $this->C_PICK: //ok
                    $this->cmdPickup($item);
                    break;
                case $this->C_LEAV: //ok
                    $this->cmdLeave($item);
                    break;
                case $this->C_EAT: //ok
                    $this->cmdEat($item);
                    break;
                case $this->C_REMO: //ok
                    $this->cmdRemove($item);
                    break;
                case $this->C_USE: //ok
                    $this->cmdUse($item);
                    break;
                case $this->C_SPEA: //ok
                    $this->cmdSpeak($item);
                    break;
                case $this->C_INVE: //ok
                    $this->cmdInventory();
                    break;
                case $this->C_LOOK: //ok
                    $this->cmdLook($item);
                    break;
                case $this->C_GOTO:
                    $this->cmdGoto($item);
                    break;

                case $this->C_HELP: //ok
                    $this->cmdHelp();
                    break;
                case $this->C_EXIT: //ok
                    $this->cmdExit();
                    break;
                default:
                    return $this->cmdDefault();
            }
        }
        if (isset($this->parser_dire[$verb])) {
            $this->curr_item = $verb;
            $this->curr_verb = $this->C_GOTO;
            $this->cmdGoto($verb);
        }
    }


    private function cmdHelp() {
        $this->arrStr2respMsg($this->game_conf["help"]);
        $this->trigger_action = "";
        $this->trigger_item = "";
    }

    private function cmdExit() {
        $this->resp_message[] = $this->txtAction($this->curr_verb, "done");
        $this->end = 1;
    }

    private function cmdDefault() {
        $this->resp_message[] = $this->txtAction("*", "wip");
    }

    private function canOpen($item) {
        if (
            isset($this->game_item[$item]) && (
                $this->game_item[$item][$this->T_POSI] == $this->player_pos ||
            $this->game_item[$item][$this->T_POSI] == $this->F_INVE
            )
        ) {
            if (isset($this->game_item[$item][$this->T_OPEN])) {
                $open = $this->game_item[$item][$this->T_OPEN];
                if ($open) {
                    return "already";
                } else {
                    return "ok";
                }
            } else {
                return "notable";
            }
        } else {
            return "notseen";
        }
    }

    private function cmdOpen($item) {
        $can = $this->canOpen($item);
        if ($can == "ok") {
            if (isset($this->game_item[$item][$this->T_ACTI][$this->C_OPEN])) {
                $list_condiz = $this->game_item[$item][$this->T_ACTI][$this->C_OPEN];
                $canDo = $this->callConditionOnEventAction($list_condiz);
                if (!$canDo) {
                    $this->resp_message[] = $this->txtAction($this->C_OPEN, "notable");
                } else {
                    $this->game_item[$item][$this->T_OPEN] = true;
                }
            } else {
                $this->game_item[$item][$this->T_OPEN] = true;
                $this->resp_message[] = $this->txtAction($this->C_OPEN, "done");
            }    
        } else {
            $this->resp_message[] = $this->txtAction($this->C_OPEN, $can);
        }
    }

    private function canClose($item) {
        if (
            isset($this->game_item[$item]) && (
            $this->game_item[$item][$this->T_POSI] == $this->player_pos ||
            $this->game_item[$item][$this->T_POSI] == $this->F_INVE
            )
        ) {
            if (isset($this->game_item[$item][$this->T_OPEN])) {
                $open = $this->game_item[$item][$this->T_OPEN];
                if ($open) {
                    return "ok";
                } else {
                    return "already";
                }
            } else {
                return "notable";
            }
        } else {
            return "notseen";
        }
    }

    private function cmdClose($item) {
        $can = $this->canClose($item);
        if ($can == "ok") {
            if (isset($this->game_item[$item][$this->T_ACTI][$this->C_CLOS])) {
                $list_condiz = $this->game_item[$item][$this->T_ACTI][$this->C_CLOS];
                $canDo = $this->callConditionOnEventAction($list_condiz);
                if (!$canDo) {
                    $this->resp_message[] = $this->txtAction($this->C_CLOS, "notable");
                } else {
                    $this->game_item[$item][$this->T_OPEN] = false;
                }
            } else {
                $this->game_item[$item][$this->T_OPEN] = false;
                $this->resp_message[] = $this->txtAction($this->C_CLOS, "done");
            }    
        } else {
            $this->resp_message[] = $this->txtAction($this->C_CLOS, $can);
        }
    }

    private function canPick($item) {
        if ( isset($this->game_item[$item]) && $this->game_item[$item][$this->T_POSI] == $this->player_pos ) {
            if (isset($this->game_item[$item][$this->T_PICK])) {
                $num_obj_inv = $this->countInventory();
                if ($num_obj_inv >= $this->inventory_max) {
                    return "fullinventory";
                } else {
                    return "ok";                    
                }
            } else {
                return "notable";
            }
        } else {
            return "notseen";            
        }
    }

    private function cmdPickup($item) {
        $can = $this->canPick($item);
        if ($can == "ok") {
            if (isset($this->game_item[$item][$this->T_ACTI][$this->C_PICK])) {
                $list_condiz = $this->game_item[$item][$this->T_ACTI][$this->C_PICK];
                $canDo = $this->callConditionOnEventAction($list_condiz);
                if (!$canDo) {
                    $this->resp_message[] = $this->txtAction($this->C_PICK, "notable");
                } else {
                    $this->game_item[$item][$this->T_POSI] = $this->F_INVE;
                }
            } else {
                $this->game_item[$item][$this->T_POSI] = $this->F_INVE;
                $this->resp_message[] = $this->txtAction($this->C_PICK, "done");
            }
        } else {
            $this->resp_message[] = $this->txtAction($this->C_PICK, $can);
        }
    }

    private function canLeave($item) {
        if ( isset($this->game_item[$item]) && $this->game_item[$item][$this->T_POSI] == $this->F_INVE ) { 
            if ( isset($this->game_item[$item][$this->T_PICK]) ) {
                return "ok";
            } else {
                return "notable";
            }
        } else {
            return "notseen";
        }
    }

    private function cmdLeave($item) {
        $can = $this->canLeave($item);
        if ($can == "ok") {
            if (isset($this->game_item[$item][$this->T_ACTI][$this->C_LEAV])) {
                $list_condiz = $this->game_item[$item][$this->T_ACTI][$this->C_LEAV];
                $canDo = $this->callConditionOnEventAction($list_condiz);
                if (!$canDo) {
                    $this->resp_message[] = $this->txtAction($this->C_LEAV, "notable");
                } else {
                    $this->game_item[$item][$this->T_POSI] = $this->player_pos;
                }
            } else {
                $this->game_item[$item][$this->T_POSI] = $this->player_pos;
                $this->resp_message[] = $this->txtAction($this->C_LEAV, "done");
            }    
        } else {
            $this->resp_message[] = $this->txtAction($this->C_LEAV, $can);
        }
    }

    private function canRemove($item) {
        if (
            $this->game_item[$item][$this->T_POSI] == $this->player_pos ||
            $this->game_item[$item][$this->T_POSI] == $this->F_INVE
        ) {
            if ( isset($this->game_item[$item][$this->T_REMO]) ) {
                return "ok";
            } else {
                return "notable";
            }
        } else {
            return "notseen";            
        }
    }

    private function cmdRemove($item) {
        $can = $this->canRemove($item);
        if ($can == "ok") {
            if (isset($this->game_item[$item][$this->T_ACTI][$this->C_REMO])) {
                $list_condiz = $this->game_item[$item][$this->T_ACTI][$this->C_REMO];
                $canDo = $this->callConditionOnEventAction($list_condiz);
                if (!$canDo) {
                    $this->resp_message[] = $this->txtAction($this->C_REMO, "notable");
                } else {
                    $this->game_item[$item][$this->T_POSI] = $this->F_REMO;
                }
            } else {
                $this->game_item[$item][$this->T_POSI] = $this->F_REMO;
                $this->resp_message[] = $this->txtAction($this->C_REMO, "done");
            }    
        } else {
            $this->resp_message[] = $this->txtAction($this->C_REMO, $can);
        }
    }

    private function canEat($item) {
        if (
            $this->game_item[$item][$this->T_POSI] == $this->player_pos ||
            $this->game_item[$item][$this->T_POSI] == $this->F_INVE
        ) {
            if ( isset($this->game_item[$item][$this->T_EAT]) ) {
                return "ok";
            } else {
                return "notable";
            }
        } else {
            return "notseen";            
        }
    }

    private function cmdEat($item) {
        $can = $this->canEat($item);
        if ($can == "ok") {
            if (isset($this->game_item[$item][$this->T_ACTI][$this->C_EAT])) {
                $list_condiz = $this->game_item[$item][$this->T_ACTI][$this->C_EAT];
                $canDo = $this->callConditionOnEventAction($list_condiz);
                if (!$canDo) {
                    $this->resp_message[] = $this->txtAction($this->C_EAT, "notable");
                } else {
                    $this->game_item[$item][$this->T_POSI] = $this->F_EAT;
                }
            } else {
                $this->game_item[$item][$this->T_POSI] = $this->F_EAT;
                $this->resp_message[] = $this->txtAction($this->C_EAT, "done");
            }    
        } else {
            $this->resp_message[] = $this->txtAction($this->C_EAT, $can);
        }
    }

    private function cmdUse($item) {
        if (isset($this->game_item[$item][$this->T_ACTI][$this->C_USE])) {
            $list_condiz = $this->game_item[$item][$this->T_ACTI][$this->C_USE];
            $canDo = $this->callConditionOnEventAction($list_condiz);
            if (!$canDo) {
                $this->resp_message[] = $this->txtAction($this->C_USE, "notable");
            } else {
                $this->resp_message[] = $this->txtAction($this->C_USE, "done");
            }
        } else {
            $this->resp_message[] = $this->txtAction($this->C_USE, "none");
        }
    }

    private function cmdSpeak($item) {
        if (isset($this->game_item[$item][$this->T_ACTI][$this->C_SPEA])) {
            $list_condiz = $this->game_item[$item][$this->T_ACTI][$this->C_SPEA];
            $canDo = $this->callConditionOnEventAction($list_condiz);
            if (!$canDo) {
                $this->resp_message[] = $this->txtAction($this->C_SPEA, "none");
            } else {
                //$this->resp_message[] = $this->txtAction($this->C_SPEA, "done");
            }
        } else {
            $this->resp_message[] = $this->txtAction($verb, "none");            
        }
    }

    private function countInventory() {
        $n_it = 0;
        foreach ($this->game_item as $k => $v) {
            if ($v[$this->T_POSI] == $this->F_INVE) {
                $n_it++;
            }
        }
        return $n_it;        
    }

    private function cmdInventory() {
        $txt = $this->txtAction($this->C_INVE, "got")."\n";
        $n_it = 0;
        foreach ($this->game_item as $k => $v) {
            if ($v[$this->T_POSI] == $this->F_INVE) {
                $txt .= "- ".$v[$this->T_DESC]."\n";
                $n_it++;
            }
        }
        if ($n_it == 0) {
            $txt .= "- ".$this->txtAction($this->curr_verb, "none")."\n";
        }
        $this->resp_message[] = $txt;
    }

    private function cmdLook($item) {
        if ( $item != "" ) {
            if (isset($this->game_item[$item])) { //exist item in game
                if ($this->game_item[$item][$this->T_POSI] == $this->player_pos ||
                $this->game_item[$item][$this->T_POSI] == $this->F_INVE ) { // and item is in current loc or in inventory
                    if (isset($this->game_item[$item][$this->T_ACTI][$this->C_LOOK])) {
                        $list_condiz = $this->game_item[$item][$this->T_ACTI][$this->C_LOOK];
                        $canDo = $this->callConditionOnEventAction($list_condiz);
                        if (!$canDo) {
                            $this->resp_message[] = $this->txtAction($this->C_LOOK, "none");
                        } else {
                            //DONE!
                            //$this->resp_message[] = $this->txtAction($this->C_LOOK, "done");
                        }
                    }
                } else {
                    $this->resp_message[] = $this->txtAction($this->curr_verb, "none");
                }
            } else {
                $this->resp_message[] = $this->txtAction($this->curr_verb, "none");
            }
        } else {
            $this->resp_message[] = $this->txtAction($this->curr_verb, "none");
        }
    }
        
    private function cmdGoto($dire) {
        $dire = $this->parser_dire[$this->curr_item];
        $available = $this->game_room[$this->player_pos][$this->T_DIRE];
        if (isset($available[$dire])) {
            $this->player_pos = $available[$dire];
            $this->trigger_action = "goto";
            $this->trigger_item = $this->player_pos;
        } else {
            $this->resp_message[] = $this->txtAction($this->curr_verb, "notable");
            $this->trigger_action = "goto_no";
        }
    }

    /**
     * Engine Event
     */

    private function evaluateSingleCondition($condiz) {
        if (strpos($condiz, ">") !== false) {
            list($op1,$op2) = explode(">",$condiz);
            if (isset($this->var[$op1])) {
                $op1 = $this->var[$op1];
            }
            if (isset($this->var[$op2])) {
                $op2 = $this->var[$op2];
            }
            return ($op1 > $op2) ? true : false;
        }
        if (strpos($condiz, "<") !== false) {
            list($op1,$op2) = explode("<",$condiz);
            if (isset($this->var[$op1])) {
                $op1 = $this->var[$op1];
            }
            if (isset($this->var[$op2])) {
                $op2 = $this->var[$op2];
            }
            return ($op1 < $op2) ? true : false;
        }
        if (strpos($condiz, "=") !== false) {
            list($op1,$op2) = explode("=",$condiz);
            if (isset($this->var[$op1])) {
                $op1 = $this->var[$op1];
            }
            if (isset($this->var[$op2])) {
                $op2 = $this->var[$op2];
            }
            return ($op1 == $op2) ? true : false;
        }
        if (strpos($condiz, "#") !== false) {// object in inventory
            $item = trim($condiz,"#");
            if($this->game_item[$item][$this->T_POSI] == "#") {
                return true;
            } else {
                return false;
            }
        }
        if (strpos($condiz, "@") !== false) { // location of player
            $loc = trim($condiz,"@");
            if($this->player_pos == $loc) {
                return true;
            } else {
                return false;
            }
        }
        if (strpos($condiz, ":") !== false) { // verb
            $verb = trim($condiz,":");
            if($this->trigger_action == $verb) {
                return true;
            } else {
                return false;
            }
        }
        return false;
    }        

    private function evaluateAndCondition($condiz) {
        if ($condiz!="") {
            $list_condiz = explode("&",$condiz);
            $resu=true;
            foreach ($list_condiz as $curr_condiz) {
                $value = $this->evaluateSingleCondition($curr_condiz);
                $resu &= $value;
            }
            return $resu;
        } else {
            return true;
        }
    }

    private function callConditionOnEventAction($cond_event_list) 
    {
        $result = true;
        foreach ($cond_event_list as $curr_cond => $event_list) {
            if ($curr_cond=='*') {
                if ($this->end == 0) {
                    $this->callEvent($event_list);
                    return true;
                }
            } else {
                $result_cond = $this->evaluateAndCondition($curr_cond);
                $result &= $result_cond;
                if ($result_cond && $this->end == 0) {
                    $this->callEvent($event_list);
                    return $result;
                }
            }
        }
        return $result;
    }

    private function callTrigger() 
    {
        if ($this->trigger_action != "") {
            foreach ($this->game_trig as $curr_cond => $event_list) {
                $result_cond = $this->evaluateAndCondition($curr_cond);
                if ($result_cond && $this->end == 0) {
                    $this->callEvent($event_list);
                }
            }
            $this->trigger_action = "";
            $this->trigger_item = "";
        }
    }

    private function callEvent($event_list) 
    {
        foreach ($event_list as $ev_cmd => $ev_par) {
            switch ($ev_cmd) {
                case "dir": // to check
                    $this->doEventDir($ev_par);
                    break;
                case "add": // to check
                    $this->doEventAdd($ev_par);
                    break;
                case "rem": // to check
                    $this->doEventRem($ev_par);
                    break;
                case "obj":
                    $this->doEventObj($ev_par);
                    break;
                case "chg":
                    $this->doEventChg($ev_par);
                    break;
                case "set":
                    $this->doEventVarSet($ev_par);
                    break;
                case "inc":
                    $this->doEventVarInc($ev_par);
                    break;
                case "dec":
                    $this->doEventVarDec($ev_par);
                    break;
                case "say":
                    $this->doEventSay($ev_par);
                    break;
                case "win":
                    $this->doEventVictory($ev_par);
                    break;
                case "lose":
                    $this->doEventDefeat($ev_par);
                    break;
                default:
            }
        }
    }

    private function doEventDir($par)
    {
        $dir = explode(":",$par);
        if (count($dir)>1) {
            $room = $obj[0];
            $mov = $obj[1];
            $room2 = "";
            if (isset($obj[2])) {
                $room2 = $obj[2];
            }
            if (isset($this->game_room[$room])) {
                if (isset($this->game_room[$room][$this->T_DIRE][$mov])) { // TODO to check
                    if (isset($this->game_room[$room2])) {
                        $this->game_room[$room][$this->T_DIRE] = $room2;
                    } else {
                        unset($this->game_room[$room][$this->T_DIRE]);
                    }
                }
            }
        }
    }

    private function doEventAdd($par)
    {
        if (isset($par[$this->T_ITEM])) {
            $item2add_name = $par[$this->T_ITEM];
            if (isset($this->game_item[$item2add_name])) {
                if ($this->game_item[$item2add_name][$this->T_POSI] == "") {
                    // dove lo metto?
                    // @ inventario
                    // oggetto -> stessa posione oggetto (se non lo trovo posizione utente)
                    // altrimenti dove si svolge l'azione - posizione utente
                    if (isset($par[$this->T_POSI])) {
                        $pos = $par[$this->T_POSI];
                        if ($pos == $this->F_INVE) {
                            $this->game_item[$item2add_name][$this->T_POSI] = $this->F_INVE;
                        } else {
                            if (isset($this->parser_item[$pos])) {
                                $this->game_item[$item2add_name][$this->T_POSI] = $this->game_item[$pos][$this->T_POSI];
                            } else {
                                $this->game_item[$item2add_name][$this->T_POSI] = $this->player_pos;
                            }
                        }
                    } else {
                        $this->game_item[$item2add_name][$this->T_POSI] = $this->player_pos;
                    }
                }
            }
        }
    }

    private function doEventObj($par)
    {
        $obj= explode(":",$par);
        if (count($obj)>1) {
            $item = $obj[0];
            $position = $obj[1];
            if (isset($this->game_item[$item])) {
                if ($position == $this->F_INVE  || $position == $this->F_REMO || isset($this->game_room[$position])) {
                    $this->game_item[$item][$this->T_POSI] = $position;
                } else {
                    if (isset($this->parser_item[$position])) {
                        $this->game_item[$item][$this->T_POSI] =  $this->game_item[$position][$this->T_POSI];
                    } else {
                        $this->game_item[$item][$this->T_POSI] = $this->player_pos;
                    }                    
                }
            }
        }
    }

    private function doEventChg($par)
    {
        if (isset($par[$this->T_ITEM])) {
            $item2chg_name = $par[$this->T_ITEM];
            if (isset($par[$this->T_DESC])) {
                $this->game_item[$item2chg_name][$this->T_DESC] = $par[$this->T_DESC];
            }
            if (isset($par[$this->T_POSI])) {
                $this->game_item[$item2chg_name][$this->T_POSI] = $par[$this->T_POSI];
            }
            if (isset($par[$this->T_OPEN])) {
                $this->game_item[$item2chg_name][$this->T_OPEN] = $par[$this->T_OPEN];
            }
        }
    }
    
    private function doEventVarSet($par)
    {
        if (is_array($par)) {
            foreach ($par as $curr_par) {
                if (is_string($curr_par)) {
                    list($var_name, $var_value) = explode(":",$curr_par);
                    $this->var[$var_name] = is_numeric($var_value) ? $var_value + 0 : $var_value;
                }                    
            }
        } else {
            if (is_string($par)) {
                list($var_name, $var_value) = explode(":",$par);
                $this->var[$var_name] = is_numeric($var_value) ? $var_value + 0 : $var_value;
            }
        }
    }

    private function doEventVarInc($par)
    {
        if (is_array($par)) {
            foreach ($par as $curr_par) {
                if (is_string($curr_par)) {
                    list($var_name, $var_value) = explode(":",$curr_par);
                    if (isset($this->var[$var_name])) {
                        $this->var[$var_name] += ($var_value + 0);
                    } else {
                        $this->var[$var_name] = ($var_value + 0);
                    }
                }                    
            }
        } else {
            if (is_string($par)) {
                list($var_name, $var_value) = explode(":",$par);
                if (isset($this->var[$var_name])) {
                    $this->var[$var_name] += ($var_value + 0);
                } else {
                    $this->var[$var_name] = ($var_value + 0);
                }
            }
        }
    }

    private function doEventVarDec($par)
    {
        if (is_array($par)) {
            foreach ($par as $curr_par) {
                if (is_string($curr_par)) {
                    list($var_name, $var_value) = explode(":",$curr_par);
                    if (isset($this->var[$var_name])) {
                        $this->var[$var_name] -= ($var_value + 0);
                    } else {
                        $this->var[$var_name] = -($var_value + 0);
                    }
                }                    
            }
        } else {
            if (is_string($par)) {
                list($var_name, $var_value) = explode(":",$par);
                if (isset($this->var[$var_name])) {
                    $this->var[$var_name] -= $var_value;
                } else {
                    $this->var[$var_name] = -($var_value + 0);
                }
            }
        }
    }

    private function doEventSay($par)
    {
        $this->arrStr2respMsg($par);   
    }

    private function doEventVictory($par)
    {
        if (isset($this->game_conf["victory"])) {
            $this->arrStr2respMsg($this->game_conf["victory"]);
        }
        $this->end = 1;
    }

    private function doEventDefeat($par)
    {
        if (isset($this->game_conf["defeat"])) {
            $this->arrStr2respMsg($this->game_conf["defeat"]);
        }
        $this->end = 1;
    }

    /**
     * Utility Engine
     */

    private function arrStr2respMsg($a) {
        if (isset($a)) {
            $this->resp_message[] = is_array($a) ? implode("",$a) : $a;
        }
    }        

    private function txtAction($verb, $msg) {
        $txt = "";
        if (isset($this->game_conf[$this->T_ACTI][$verb][$msg])) {
            $txt = $this->game_conf[$this->T_ACTI][$verb][$msg];
        } else if (isset($this->game_conf[$this->T_ACTI]["*"][$msg])) {
            $txt = $this->game_conf[$this->T_ACTI]["*"][$msg];
        } else {
            $txt = "***MSG.".$verb.".".$msg;
        }
        return $txt;
    }
    
    /**
     * Utility Method
     */

    private function getOnlyAlias($a) {
        $dizio = [];
        if (count($a)>0) {
            foreach ($a as $k =>$v) {
                if (isset($v["alias"])) {
                    $alias = $v["alias"];
                    foreach ($alias as $ali) {
                        $dizio[$ali] = $k;
                    }
                }
            }
        }
        return $dizio;
    }

    private function getTokenAlias($a) {
        $dizio = [];
        if (count($a)>0) {
            foreach ($a as $k =>$v) {
                $dizio[$k] = $k;
                if (isset($v["alias"])) {
                    $alias = $v["alias"];
                    foreach ($alias as $ali) {
                        $dizio[$ali] = $k;
                    }
                }
            }
        }
        return $dizio;
    }

    private function getTokenArray($a) {
        $dizio = [];
        if (count($a)>0) {
            foreach ($a as $k =>$v) {
                $dizio[$k] = $k;
                foreach ($v as $b) {
                    $dizio[$b] = $k;
                }
            }
        }
        return $dizio;
    }

}