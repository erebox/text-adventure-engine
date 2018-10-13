<?php

namespace Erebox\TextAdventureEngine;

class Engine
{

    protected $resp_message = [];
    protected $resp_status = [];

    protected $status = 0;
    protected $end = 0;    
    protected $player_pos = "";
    protected $inventory_max = 10;

    protected $inventory = [];
    protected $game = [];
    protected $var = [];

    protected $game_init = [];
    

    protected $parser_igno = [];
    protected $parser_verb = [];
    protected $parser_dire = [];
    protected $parser_item = [];

    protected $curr_verb = "";
    protected $curr_item = "";

    protected $trigger_action = "";
    protected $trigger_item = "";

    //Tag
    protected $T_ROOM   = "room";
    protected $T_ITEM   = "item";
    protected $T_DESC   = "description";
    protected $T_DETA   = "detail";
    protected $T_EXIT   = "direction";
    protected $T_POSI   = "position";
    protected $T_OPEN   = "open";
    protected $T_PICK   = "pickable";
    protected $T_REMO   = "removable";
    protected $T_EAT    = "eatable";
    protected $T_ACTI   = "action";
    protected $T_TYPE   = "type";
    protected $T_VALU   = "value";
    protected $T_TRIG   = "trigger";
    //Flag
    protected $F_INVE   = "@";
    protected $F_REMO   = "#";
    //Command
    protected $C_OPEN   = "open";
    protected $C_CLOS   = "close";
    protected $C_PICK   = "pickup";
    protected $C_LEAV   = "leave";
    protected $C_INVE   = "inventory";
    protected $C_LOOK   = "look";
    protected $C_GOTO   = "goto";
    protected $C_REMO   = "remove";
    protected $C_EXIT   = "exit";
    protected $C_LOAD   = "load";
    protected $C_SAVE   = "save";
    protected $C_HELP   = "help";
    protected $C_EAT    = "eat";
    protected $C_SPEA   = "speak";
    protected $C_STAT   = "status";

    protected $V_VERS   = "1.0.0";
    
    public function __construct($jsongame = null)
    {
        $this->load($jsongame);
    }

    public function load($jsongame = null) 
    {
        if ($jsongame) {
            $this->game = json_decode($jsongame, true);
            $this->game_init = $jsongame;
        }
        if (count($this->game)>0) {
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
        $this->parseSentence($sentence);
    }


    public function ended() {
        return ($this->end == 1);
    }

    public function debug() 
    {
        //TODO
        return $this->game;
    }

    public function version() 
    {
        return $this->V_VERS;
    }


    /**
     *  Private
     */
    
    private function setup()
    {
        $this->player_pos = $this->game["config"]["init_room"];
        if (isset($this->game["config"]["inventory_max"])) {
            $this->inventory_max = $this->game["config"]["inventory_max"];            
        }
        
        $this->parser_igno = array_flip($this->game["config"]["ignore"]);
        $this->parser_dire = $this->getTokenArray($this->game["config"][$this->T_EXIT]);
        
        $this->parser_verb = $this->getOnlyAlias($this->game[$this->T_ACTI]);
        $this->parser_item = $this->getTokenAlias($this->game[$this->T_ITEM]);

        if (isset($this->game["config"]["init_var"])) {
            $var2set = $this->game["config"]["init_var"];
            foreach ( $var2set as $curr_var2set) {
                list($curr_var,$curr_val) = explode(":", $curr_var2set);
                $this->var[$curr_var] = $curr_val;
            }
        }
    }

    private function intro()
    {
        $this->arrStr2respMsg($this->game["config"]["intro"]);
    }

    private function go()
    {
        $this->callTrigger();
        if ($this->end == 0) {
            $dove = $this->game[$this->T_ROOM][$this->player_pos];
            $txt = $this->txtAction("*", "where").$dove[$this->T_DESC].".\n";
            foreach ($this->game[$this->T_ITEM] as  $k =>$v) {
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
            //$this->Log("".$this->curr_verb." - ".$this->curr_item);
            
            switch ($this->curr_verb) {
                case $this->C_OPEN:
                    $this->cmdOpen();
                    break;
                case $this->C_CLOS:
                    $this->cmdClose();
                    break;
                case $this->C_PICK:
                    $this->cmdPickup();
                    break;
                case $this->C_LEAV:
                    $this->cmdLeave();
                    break;
                case $this->C_INVE:
                    $this->cmdInventory();
                    break;
                case $this->C_LOOK:
                    $this->cmdLook();
                    break;
                case $this->C_GOTO:
                    $this->cmdGoto();
                    break;
                case $this->C_STAT:
                    $this->cmdStatus();
                    break;
                case $this->C_EAT:
                    $this->cmdEat();
                    break;
                case $this->C_SPEA:
                    $this->cmdSpeak();
                    break;
                case $this->C_REMO:
                    $this->cmdRemove();
                    break;
                case $this->C_EXIT:
                    $this->cmdExit();
                    break;
                case $this->C_HELP:
                    $this->cmdHelp();
                    break;
                case $this->C_LOAD:
                case $this->C_SAVE:
                    $this->cmdDefault();
                    break;
                default:
                    return $this->cmdDefault();
            }
        }
        if (isset($this->parser_dire[$verb])) {
            $this->curr_item = $verb;
            $this->curr_verb = $this->C_GOTO;
            $this->cmdGoto();
        }
    }

    private function cmdDefault() {
        $this->resp_message[] = $this->txtAction("*", "wip");
    }

    private function cmdHelp() {
        $this->arrStr2respMsg($this->game["config"]["help"]);
        $this->trigger_action = "";
        $this->trigger_item = "";
    }

    private function cmdExit() {
        $this->resp_message[] = $this->txtAction($this->curr_verb, "done");
        $this->end = 1;
    }
        
    private function canOpen($item) {
        if (
            isset($this->game[$this->T_ITEM][$item]) && (
                $this->game[$this->T_ITEM][$item][$this->T_POSI] == $this->player_pos ||
            $this->game[$this->T_ITEM][$item][$this->T_POSI] == $this->F_INVE
            )
        ) {
            if (isset($this->game[$this->T_ITEM][$item][$this->T_OPEN])) {
                $open = $this->game[$this->T_ITEM][$item][$this->T_OPEN];
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

    private function cmdOpen() {
        $verb = $this->curr_verb;
        $item = $this->curr_item;
        $can = $this->canOpen($item);
        if ($can == "ok") {
            if (isset($this->game[$this->T_ITEM][$item][$this->T_ACTI][$verb])) {
                // ci sono condizioni eventi sull'azione
                $list_condiz = $this->game[$this->T_ITEM][$item][$this->T_ACTI][$verb];
                $canDo = $this->callCondEvent($list_condiz);
                if (!$canDo) {
                    $this->resp_message[] = $this->txtAction($verb, "notable");
                }
            } else {
                $this->game[$this->T_ITEM][$item][$this->T_OPEN] = true;
                $this->resp_message[] = $this->txtAction($verb, "done");
            }    
        } else {
            $this->resp_message[] = $this->txtAction($verb, $can);
        }
    }

    private function canClose($item) {
        if (
            isset($this->game[$this->T_ITEM][$item]) && (
            $this->game[$this->T_ITEM][$item][$this->T_POSI] == $this->player_pos ||
            $this->game[$this->T_ITEM][$item][$this->T_POSI] == $this->F_INVE
            )
        ) {
            if (isset($this->game[$this->T_ITEM][$item][$this->T_OPEN])) {
                $open = $this->game[$this->T_ITEM][$item][$this->T_OPEN];
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

    private function cmdClose() {
        $verb = $this->curr_verb;
        $item = $this->curr_item;
        $can = $this->canClose($item);
        if ($can == "ok") {
            if (isset($this->game[$this->T_ITEM][$item][$this->T_ACTI][$verb])) {
                // ci sono condizioni eventi sull'azione
                $list_condiz = $this->game[$this->T_ITEM][$item][$this->T_ACTI][$verb];
                $canDo = $this->callCondEvent($list_condiz);
                if (!$canDo) {
                    $this->resp_message[] = $this->txtAction($verb, "notable");
                }
            } else {
                $this->game[$this->T_ITEM][$item][$this->T_OPEN] = false;
                $this->resp_message[] = $this->txtAction($verb, "done");
            }    
        } else {
            $this->resp_message[] = $this->txtAction($verb, $can);
        }
    }

    private function canPick($item) {
        if (
            isset($this->game[$this->T_ITEM][$item]) &&
            $this->game[$this->T_ITEM][$item][$this->T_POSI] == $this->player_pos
        ) {
            if (isset($this->game[$this->T_ITEM][$item][$this->T_PICK]) &&
                $this->game[$this->T_ITEM][$item][$this->T_PICK]
            ) {
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

    private function cmdPickup() {
        $verb = $this->curr_verb;
        $item = $this->curr_item;
        $can = $this->canPick($item);
        if ($can == "ok") {
            if (isset($this->game[$this->T_ITEM][$item][$this->T_ACTI][$verb])) {
                // ci sono condizioni eventi sull'azione
                $list_condiz = $this->game[$this->T_ITEM][$item][$this->T_ACTI][$verb];
                $canDo = $this->callCondEvent($list_condiz);
                if (!$canDo) {
                    $this->resp_message[] = $this->txtAction($verb, "notable");
                }
            } else {
                $this->game[$this->T_ITEM][$item][$this->T_POSI] = $this->F_INVE;
                $this->resp_message[] = $this->txtAction($verb, "done");
            }
        } else {
            $this->resp_message[] = $this->txtAction($verb, $can);
        }
    }

    private function canLeave($item) {
        if (
            isset($this->game[$this->T_ITEM][$item]) &&
            $this->game[$this->T_ITEM][$item][$this->T_POSI] == $this->F_INVE
        ) {
            return "ok";
        } else {
            return "notseen";            
        }
    }

    private function cmdLeave() {
        $verb = $this->curr_verb;
        $item = $this->curr_item;
        $can = $this->canLeave($item);
        if ($can == "ok") {
            if (isset($this->game[$this->T_ITEM][$item][$this->T_ACTI][$verb])) {
                // ci sono condizioni eventi sull'azione
                $list_condiz = $this->game[$this->T_ITEM][$item][$this->T_ACTI][$verb];
                $canDo = $this->callCondEvent($list_condiz);
                if (!$canDo) {
                    $this->resp_message[] = $this->txtAction($verb, "notable");
                }
            } else {
                $this->game[$this->T_ITEM][$item][$this->T_POSI] = $this->player_pos;
                $this->resp_message[] = $this->txtAction($verb, "done");
            }    
        } else {
            $this->resp_message[] = $this->txtAction($verb, $can);
        }
    }

    private function cmdSpeak() {
        $verb = $this->curr_verb;
        $item = $this->curr_item;
        if (isset($this->game[$this->T_ITEM][$item][$this->T_ACTI][$verb])) {
            $list_condiz = $this->game[$this->T_ITEM][$item][$this->T_ACTI][$verb];
            $canDo = $this->callCondEvent($list_condiz);
        } else {
            $this->resp_message[] = $this->txtAction($verb, "none");            
        }
    }

    private function canRemove($item) {
        if (
            $this->game[$this->T_ITEM][$item][$this->T_POSI] == $this->player_pos ||
            $this->game[$this->T_ITEM][$item][$this->T_POSI] == $this->F_INVE
        ) {
            if (isset($this->game[$this->T_ITEM][$item][$this->T_REMO]) &&
                $this->game[$this->T_ITEM][$item][$this->T_REMO]
            ) {
                return "ok";
            } else {
                return "notable";
            }
        } else {
            return "notseen";            
        }
    }

    private function cmdRemove() {
        $verb = $this->curr_verb;
        $item = $this->curr_item;
        $can = $this->canRemove($item);
        if ($can == "ok") {
            if (isset($this->game[$this->T_ITEM][$item][$this->T_ACTI][$verb])) {
                // ci sono condizioni eventi sull'azione
                $list_condiz = $this->game[$this->T_ITEM][$item][$this->T_ACTI][$verb];
                $canDo = $this->callCondEvent($list_condiz);
                if (!$canDo) {
                    $this->resp_message[] = $this->txtAction($verb, "notable");
                }
            } else {
                $this->game[$this->T_ITEM][$item][$this->T_POSI] = $this->F_REMO;
                $this->resp_message[] = $this->txtAction($verb, "done");
            }    
        } else {
            $this->resp_message[] = $this->txtAction($verb, $can);
        }
    }

    private function canEat($item) {
        if (
            $this->game[$this->T_ITEM][$item][$this->T_POSI] == $this->player_pos ||
            $this->game[$this->T_ITEM][$item][$this->T_POSI] == $this->F_INVE
        ) {
            if (isset($this->game[$this->T_ITEM][$item][$this->T_EAT]) &&
                $this->game[$this->T_ITEM][$item][$this->T_EAT]
            ) {
                return "ok";
            } else {
                return "notable";
            }
        } else {
            return "notseen";            
        }
    }

    private function cmdEat() {
        $verb = $this->curr_verb;
        $item = $this->curr_item;
        $can = $this->canEat($item);
        if ($can == "ok") {
            if (isset($this->game[$this->T_ITEM][$item][$this->T_ACTI][$verb])) {
                // ci sono condizioni eventi sull'azione
                $list_condiz = $this->game[$this->T_ITEM][$item][$this->T_ACTI][$verb];
                $canDo = $this->callCondEvent($list_condiz);
                if (!$canDo) {
                    $this->resp_message[] = $this->txtAction($verb, "notable");
                }
            } else {
                $this->game[$this->T_ITEM][$item][$this->T_POSI] = $this->T_EAT;
                $this->resp_message[] = $this->txtAction($verb, "done");
            }    
        } else {
            $this->resp_message[] = $this->txtAction($verb, $can);
        }
    }


    private function countInventory() {
        $n_it = 0;
        foreach ($this->game[$this->T_ITEM] as $k => $v) {
            if ($v[$this->T_POSI] == $this->F_INVE) {
                $n_it++;
            }
        }
        return $n_it;        
    }

    private function cmdInventory() {
        $txt = $this->txtAction($this->curr_verb, "got")."\n";
        $n_it = 0;
        foreach ($this->game[$this->T_ITEM] as $k => $v) {
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

    private function cmdLook() {
        if ( $this->curr_item!="" ) {
            if (isset($this->game[$this->T_ITEM][$this->curr_item])) {
                if ($this->game[$this->T_ITEM][$this->curr_item][$this->T_POSI] == $this->player_pos ||
                $this->game[$this->T_ITEM][$this->curr_item][$this->T_POSI] == $this->F_INVE ) {
                    $this->resp_message[] = $this->game[$this->T_ITEM][$this->curr_item][$this->T_DETA];
                } else {
                    $this->resp_message[] = $this->txtAction($this->curr_verb, "none");
                }
            } elseif (isset($this->game[$this->T_ROOM][$this->curr_item])) {
                if ($this->player_pos == $this->curr_item) {
                    if (isset($this->game[$this->T_ROOM][$this->curr_item][$this->T_DETA])) {
                        $this->resp_message[] = $this->game[$this->T_ROOM][$this->curr_item][$this->T_DETA];
                    } else {
                        $this->resp_message[] = $this->game[$this->T_ROOM][$this->curr_item][$this->T_DESC];
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
        
    private function cmdGoto() {
        $dire = $this->parser_dire[$this->curr_item];
        if (isset($this->game[$this->T_ROOM][$this->player_pos][$this->T_EXIT])) {
            $available = $this->game[$this->T_ROOM][$this->player_pos][$this->T_EXIT];
            if (isset($available[$dire])) {
                $this->player_pos = $available[$dire];
                $this->trigger_action = "goto";
                $this->trigger_item = $this->player_pos;
            } else {
                $this->resp_message[] = $this->txtAction($this->curr_verb, "notable");
                $this->trigger_action = "goto_no";
            }
        } else {
            $this->resp_message[] = $this->txtAction($this->curr_verb, "notable");
            $this->trigger_action = "goto_no";        
        }
    }

    private function cmdStatus() {
        $this->resp_message[] = "STATUS: ".print_r($this->var,1);
    }

    /**
     * Engine Event
     */

    private function callTrigger() 
    {
        if ($this->trigger_action != "") {
            if (isset($this->game[$this->T_TRIG]["*"])) {
                $list_event = $this->game[$this->T_TRIG]["*"];
                $this->callCondEvent($list_event);
            }
            if (isset($this->game[$this->T_TRIG][$this->trigger_action])) {
                $list_event = $this->game[$this->T_TRIG][$this->trigger_action];
                $this->callCondEvent($list_event);
            }
            $this->trigger_action = "";
            $this->trigger_item = "";
        }
    }

    private function evalSingleCond($condiz) {
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
        if (strpos($condiz, "@") !== false) {
            $item = trim($condiz,"@");
            if($this->game[$this->T_ITEM][$item][$this->T_POSI] == "@") {
                return true;
            } else {
                return false;
            }
        }
        if (strpos($condiz, "#") !== false) {
            $loc = trim($condiz,"#");
            if($this->player_pos == $loc) {
                return true;
            } else {
                return false;
            }
        }
        return false;
    }        

    private function evalCond($cond) {
        list($tag,$condiz)=explode(":",trim($cond));
        if (strtolower($tag)=="cond") {
            if ($condiz!="") {
                $list_condiz = explode("&",$condiz);
                $resu=true;
                foreach ($list_condiz as $curr_condiz) {
                    $value = $this->evalSingleCond($curr_condiz);
                    $resu &= $value;
                }
                return $resu;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }

    private function callCondEvent($cond_event_list) 
    {
        $result = true;
        foreach ($cond_event_list as $curr_cond => $event_list) {
            $result_cond = $this->evalCond($curr_cond);
            if ($result_cond && $this->end == 0) {
                $this->callEvent($event_list);
            }
            $result &= $result_cond;
        }
        return $result;
    }

    private function callEvent($event_list) 
    {
        foreach ($event_list as $ev_cmd => $ev_par) {
            switch ($ev_cmd) {
                case "obj":
                    $this->doEventObj($ev_par);
                    break;
                case "dir":
                    $this->doEventDir($ev_par);
                    break;
                case "add":
                    $this->doEventAdd($ev_par);
                    break;
                case "rem":
                    $this->doEventRem($ev_par);
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
        //$this->log($this->game);
    }

    private function doEventSay($par)
    {
        $this->arrStr2respMsg($par);   
    }

    private function doEventAdd($par)
    {
        if (isset($par[$this->T_ITEM])) {
            $item2add_name = $par[$this->T_ITEM];
            if (isset($this->game[$this->T_ITEM][$item2add_name])) {
                if ($this->game[$this->T_ITEM][$item2add_name][$this->T_POSI] == "") {
                    // dove lo metto?
                    // @ inventario
                    // oggetto -> stessa posione oggetto (se non lo trovo posizione utente)
                    // altrimenti dove si svolge l'azione - posizione utente
                    if (isset($par[$this->T_POSI])) {
                        $pos = $par[$this->T_POSI];
                        if ($pos == $this->F_INVE) {
                            $this->game[$this->T_ITEM][$item2add_name][$this->T_POSI] = $this->F_INVE;
                        } else {
                            if (isset($this->parser_item[$pos])) {
                                $this->game[$this->T_ITEM][$item2add_name][$this->T_POSI] = $this->game[$this->T_ITEM][$pos][$this->T_POSI];
                            } else {
                                $this->game[$this->T_ITEM][$item2add_name][$this->T_POSI] = $this->player_pos;
                            }
                        }
                    } else {
                        $this->game[$this->T_ITEM][$item2add_name][$this->T_POSI] = $this->player_pos;
                    }
                }
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
            if (isset($this->game[$this->T_ROOM][$room])) {
                if (isset($this->game[$this->T_EXIT][$mov])) {
                    if (isset($this->game[$this->T_ROOM][$room2])) {
                        $this->game[$this->T_ROOM][$room][$this->T_EXIT] = $room2;
                    } else {
                        unset($this->game[$this->T_ROOM][$room][$this->T_EXIT]);
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
            if (isset($this->game[$this->T_ITEM][$item])) {
                if ($position == $this->F_INVE  || $position == $this->F_REMO || isset($this->game[$this->T_ROOM][$position])) {
                    $this->game[$this->T_ITEM][$item][$this->T_POSI] = $position;
                } else {
                    if (isset($this->parser_item[$position])) {
                        $this->game[$this->T_ITEM][$item][$this->T_POSI] =  $this->game[$this->T_ITEM][$position][$this->T_POSI];
                    } else {
                        $this->game[$this->T_ITEM][$item][$this->T_POSI] = $this->player_pos;
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
                $this->game[$this->T_ITEM][$item2chg_name][$this->T_DESC] = $par[$this->T_DESC];
            }
            if (isset($par[$this->T_DETA])) {
                $this->game[$this->T_ITEM][$item2chg_name][$this->T_DETA] = $par[$this->T_DETA];
            }
            if (isset($par[$this->T_POSI])) {
                $this->game[$this->T_ITEM][$item2chg_name][$this->T_POSI] = $par[$this->T_POSI];
            }
            if (isset($par[$this->T_OPEN])) {
                $this->game[$this->T_ITEM][$item2chg_name][$this->T_OPEN] = $par[$this->T_OPEN];
            }
        }
    }

    private function doEventRem($par)
    {
    }

    private function doEventVarSet($par)
    {
        if (is_array($par)) {
            foreach ($par as $curr_par) {
                if (is_string($curr_par)) {
                    list($var_name, $var_value) = explode(":",$curr_par);
                    $this->var[$var_name] = $var_value;
                }                    
            }
        } else {
            if (is_string($par)) {
                list($var_name, $var_value) = explode(":",$par);
                $this->var[$var_name] = $var_value;
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
                        $this->var[$var_name] += $var_value;
                    } else {
                        $this->var[$var_name] = $var_value;
                    }
                }                    
            }
        } else {
            if (is_string($par)) {
                list($var_name, $var_value) = explode(":",$par);
                if (isset($this->var[$var_name])) {
                    $this->var[$var_name] += $var_value;
                } else {
                    $this->var[$var_name] = $var_value;
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
                        $this->var[$var_name] -= $var_value;
                    } else {
                        $this->var[$var_name] = -$var_value;
                    }
                }                    
            }
        } else {
            if (is_string($par)) {
                list($var_name, $var_value) = explode(":",$par);
                if (isset($this->var[$var_name])) {
                    $this->var[$var_name] -= $var_value;
                } else {
                    $this->var[$var_name] = -$var_value;
                }
            }
        }
    }

    private function doEventVictory($par)
    {
        $this->arrStr2respMsg($this->game["config"]["victory"]);
        $this->end = 1;
    }

    private function doEventDefeat($par)
    {
        $this->arrStr2respMsg($this->game["config"]["defeat"]);
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
        if (isset($this->game[$this->T_ACTI][$verb][$msg])) {
            $txt = $this->game[$this->T_ACTI][$verb][$msg];
        } else if (isset($this->game[$this->T_ACTI]["*"][$msg])) {
            $txt = $this->game[$this->T_ACTI]["*"][$msg];
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
        foreach ($a as $k =>$v) {
            if (isset($v["alias"])) {
                $alias = $v["alias"];
                foreach ($alias as $ali) {
                    $dizio[$ali] = $k;
                }
            }
        }
        return $dizio;
    }

    private function getTokenAlias($a) {
        $dizio = [];
        foreach ($a as $k =>$v) {
            $dizio[$k] = $k;
            if (isset($v["alias"])) {
                $alias = $v["alias"];
                foreach ($alias as $ali) {
                    $dizio[$ali] = $k;
                }
            }
        }
        return $dizio;
    }

    private function getTokenArray($a) {
        $dizio = [];
        foreach ($a as $k =>$v) {
            $dizio[$k] = $k;
            foreach ($v as $b) {
                $dizio[$b] = $k;
            }
        }
        return $dizio;
    }

    private function log($l) {
        if ($this->debug) {
            $f = "engine.log";
            if (is_array($l)) {
                $l = print_r($l,1);
            }
            $c ="[".date("Y-m-d H:i:s")."] ".$l."\n";
            //Storage::append($f, $c);
            //savetofile
        }
    }

}