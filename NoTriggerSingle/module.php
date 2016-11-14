<?
class NoTriggerSingle extends IPSModule {

    public function __construct($InstanceID) {

        //Never delete this line!
        parent::__construct($InstanceID);
        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        /*
  RegisterProperty('Active', false);
  RegisterProperty('MultipleAlert', false);
  RegisterProperty('VarID', 0);
  RegisterProperty('Timer', 0);
  RegisterProperty('ScriptID', 0);
  RegisterProperty('HasState',true);
  RegisterProperty('StartUp',0);
  RegisterProperty('CheckMode',0);
  RegisterTimer('NoTrigger', 0, TimerFire);
  */
    }

    public function ApplyChanges() {
        //Never delete this line!
        parent::ApplyChanges();
    }

################## PRIVATE     

################## ActionHandler

    public function ActionHandler($StatusVariableIdent, $Value) {
    }

################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */

    public function SendSwitch($State) {
    }


################## DUMMYS / WOARKAROUNDS - protected




}

?>