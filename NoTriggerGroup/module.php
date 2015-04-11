<?
class NoTriggerGroup extends IPSModule {

    public function __construct($InstanceID) {

        //Never delete this line!
        parent::__construct($InstanceID);
        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
  /*RegisterProperty('Active', false);
  RegisterProperty('MultipleAlert', false);
  RegisterProperty('Timer', 0);
  RegisterProperty('ScriptID', 0);
  RegisterProperty('HasState',true);
  RegisterProperty('StartUp',0);
  RegisterProperty('CheckMode',0);

  RegisterTimer('NoTrigger (Group)', 0, TimerFire); //every 30 sek


  Alerts:=0;
  ActiveVarID:=0;
  IPSVarIDs:= TNoTriggerVarList.Create();        
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

    protected function HasActiveParent() {
        $id = @IPS_GetInstanceParentID($this->InstanceID);
        if ($id > 0) {
            if (IPS_GetInstance($id)['InstanceStatus'] == IS_ACTIVE)
                return true;
            else
                return false;
        }
    }

    protected function SetStatus($data) {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
    }

    protected function RegisterTimer($data, $cata) {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
    }

    protected function SetTimerInterval($data, $cata) {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
    }

    protected function LogMessage($data, $cata) {
        
    }

    protected function SetSummary($data) {
        IPS_LogMessage(__CLASS__, __FUNCTION__ . "Data:" . $data); //                   
    }

}

?>