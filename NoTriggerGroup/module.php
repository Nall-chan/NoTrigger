<?

/*
 * @addtogroup notrigger
 * @{
 *
 * @package       NoTrigger
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2016 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       1.0
 *
 */

require_once(__DIR__ . "/../NoTriggerBase.php");

class TNoTriggerVar
{

    public $VarId = 0;
    public $LinkId = 0;
    public $Alert = false;

    public function __construct(int $VarId, int $LinkId, bool $Alert)
    {
        $this->VarId = $VarId;
        $this->LinkId = $LinkId;
        $this->Alert = $Alert;
    }

}

/**
 * @property Array $Items Liste mit allen Variablen
 */
class TNoTriggerVarList
{

    public $Items = array();

    /* public function __construct($array)
      {
      if (is_array($array)) {
      $this->Items = $array;
      }
      } */

    public function __sleep()
    {
        return array('Items');
    }

    public function Add(TNoTriggerVar $NoTriggerVar)
    {
        $this->Items[] = $NoTriggerVar;
    }

    public function Remove(int $Index)
    {
        unset($this->Items[$Index]);
    }

    /**
     * 
     * @param int $Index
     * @return TNoTriggerVar
     */
    public function Get(int $Index)
    {
        return $this->Items[$Index];
    }

    public function IndexOfVarID(int $VarId)
    {
        foreach ($this->Items as $Index => $NoTriggerVar)
        {
            if ($NoTriggerVar->VarId == $VarId)
                return $Index;
        }
        return false;
    }

    public function IndexOfLinkID(int $LinkId)
    {
        foreach ($this->Items as $Index => $NoTriggerVar)
        {
            if ($NoTriggerVar['LinkId'] == $LinkId)
                return $Index;
        }
        return false;
    }

}

/**
 * NoTrigger Klasse für die die Überwachung von mehreren Variablen auf fehlende Änderung/Aktualisierung.
 * Erweitert NoTriggerBase.
 *
 * @package       NoTrigger
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2016 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       1.0
 * @example <b>Ohne</b>
 *
 * @property int $Alerts Anzahl der Alarme
 * @property int $ActiveVarID Anzahl der aktiven Vars
 * @property TNoTriggerVarList $NoTriggerVarList Liste mit allen Variablen
 */
class NoTriggerGroup extends NoTriggerBase
{

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('Active', false);
        $this->RegisterPropertyBoolean('MultipleAlert', false);
        $this->RegisterPropertyInteger('Timer', 0);
        $this->RegisterPropertyInteger('ScriptID', 0);
        $this->RegisterPropertyBoolean('HasState', true);
        $this->RegisterPropertyInteger('StartUp', 0);
        $this->RegisterPropertyInteger('CheckMode', 0);

        $this->Alerts = 0;
        $this->ActiveVarID = 0;
        $this->NoTriggerVarList = new TNoTriggerVarList();
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug('Message:SenderID', $SenderID, 0);
        $this->SendDebug('Message:Message', $Message, 0);
        $this->SendDebug('Message:Data', $Data, 0);
        switch ($Message)
        {
            case IPS_KERNELMESSAGE:
                switch ($Data[0])
                {
                    case KR_READY:
                        $this->GetAllTargets();
                        switch ($this->ReadPropertyInteger('StartUp'))
                        {
                            case 0:
                                if ($this->CheckConfig())
                                    $this->StartTimer();
                                break;
                            case 1:
                                if ($this->CheckConfig())
                                    $this->SetTimerInterval('NoTrigger', $this->ReadPropertyInteger('Timer') * 1000);
                                break;
                        }
                        break;
                    case KR_UNINIT:
                        $this->StopTimer();
                        break;
                }
                break;
            case VM_UPDATE:
                // prüfen ob in liste und auswerten wegen Ruhemeldung wenn Alarm war
                $TriggerVarList = $this->NoTriggerVarList;
                $Index = $TriggerVarList->IndexOfVarID($SenderID);
                if ($Index === false)
                    return;
                $NoTriggerVar = $TriggerVarList->Get($Index);
                if ($NoTriggerVar->Alert and ( ($Data[1] == true) or ( $this->ReadPropertyInteger('CheckMode') == 0)))
                {
                    $TriggerVarList->Items[$Index]->Alert = false;
                    $this->NoTriggerVarList = $TriggerVarList;
                    $this->Alerts--;
                    if ($this->Alerts == 0) //war letzter Alarm dann ruhe senden uns status setzen
                    {
                        $this->DoScript($NoTriggerVar->VarId, false, true);
                        $this->SetStateVar(false, $NoTriggerVar->VarId);
                    }
                    else
                    { // noch mehr in Alarm, also kein state setzen und...
                        if ($this->ReadPropertyBoolean('MultipleAlert')) //bei mehrfach auch ruhe sende
                        {
                            $this->DoScript($NoTriggerVar->VarId, false, true);
                        }
                    }
                }

                // Var das die aktive Var für den Timer ? Dann Timer neu berechnen
                $ActiveVarID = $this->ActiveVarID;
                if ((($SenderID == $ActiveVarID) or ( $ActiveVarID == 0)) and ( ($Data[1] === true) or ( $this->ReadPropertyInteger('CheckMode') == 0))) //Update von Var welche gerade den Timer steuert
                {
                    $this->StartTimer();         // neue Var für timer festlegen und timer starten
                }
                break;
            case VM_DELETE:
                $this->UnregisterVariableWatch($SenderID);
                // prüfen ob in liste und auswerten wegen Ruhemeldung wenn Alarm war
                $TriggerVarList = $this->NoTriggerVarList;
                $Index = $TriggerVarList->IndexOfVarID($SenderID);
                if ($Index === false)
                    return;
                $NoTriggerVar = $TriggerVarList->Get($Index);

                //und jetzt gleich prüfen ob vorher alarm und merken
                if ($NoTriggerVar->Alert)
                {
                    $this->Alerts--;
                    if ($this->Alerts == 0) //war letzter Alarm dann ruhe senden uns status setzen
                    {
                        $this->DoScript($NoTriggerVar->VarId, false, true);
                        $this->SetStateVar(false, $NoTriggerVar->VarId);
                    }
                    else
                    { // noch mehr in Alarm, also kein state setzen und...
                        if ($this->ReadPropertyBoolean('MultipleAlert')) //bei mehrfach auch ruhe sende
                        {
                            $this->DoScript($NoTriggerVar->VarId, false, true);
                        }
                    }
                }
                $TriggerVarList->Remove($Index);
                $this->NoTriggerVarList = $TriggerVarList;
                if (count($TriggerVarList->Items) == 0)
                {
                    $this->ActiveVarID = 0;
                    $this->SetStatus(203);
                    $this->StopTimer();
                    return;
                }
                $ActiveVarID = $this->ActiveVarID;
                if (($SenderID == $ActiveVarID) or ( $ActiveVarID == 0))
                {
                    $this->StartTimer();         // neue Var für timer festlegen und timer starten
                }
                break;
            case LM_CHANGETARGET:
                // prüfen ob in liste und auswerten wegen Ruhemeldung wenn Alarm war
                $TriggerVarList = $this->NoTriggerVarList;
                $Index = $TriggerVarList->IndexOfLinkID($SenderID);
                if ($Index === false)
                    return;
                $NoTriggerVar = $TriggerVarList->Get($Index);
                $this->UnregisterVariableWatch($NoTriggerVar->VarId);
                //und jetzt gleich prüfen ob vorher alarm und merken
                if ($NoTriggerVar->Alert)
                {
                    $this->Alerts--;
                    if ($this->Alerts == 0) //war letzter Alarm dann ruhe senden uns status setzen
                    {
                        $this->DoScript($NoTriggerVar->VarId, false, true);
                        $this->SetStateVar(false, $NoTriggerVar->VarId);
                    }
                    else
                    { // noch mehr in Alarm, also kein state setzen und...
                        if ($this->ReadPropertyBoolean('MultipleAlert')) //bei mehrfach auch ruhe sende
                        {
                            $this->DoScript($NoTriggerVar->VarId, false, true);
                        }
                    }
                }
                $TriggerVarList->Remove($Index);
                // Prüfen ob Ziel eigener State ist
                if ($this->ReadPropertyBoolean('HasState'))
                {
                    if ($this->GetIDForIdent('STATE') == $Data[0])
                    {
                        $this->SetStatus(204); //State ist in den Links
                        $this->StopTimer();
                        return;
                    }
                }
                if ($Data[0] > 0)
                {
                    $Target = IPS_GetObject($Data[0]);
                    if ($Target['ObjectType'] != otVariable)
                        $Data[0] = 0;
                }
                if ($Data[0] == 0)
                {
                    if (count($TriggerVarList->Items) == 0)
                    {
                        $this->NoTriggerVarList = $TriggerVarList;
                        $this->ActiveVarID = 0;
                        $this->SetStatus(203);
                        $this->StopTimer();
                        return;
                    }
                }
                $NoTriggerVar = new TNoTriggerVar($Data[0], $SenderID, FALSE);
                $TriggerVarList->Add($NoTriggerVar);
                $this->NoTriggerVarList = $TriggerVarList;
                $this->RegisterVariableWatch($Data[0]);


                $ActiveVarID = $this->ActiveVarID;
                if (($NoTriggerVar->VarId == $ActiveVarID) or ( $ActiveVarID == 0))
                    $this->StartTimer();         // alte Var war aktiv oder gar keine

                break;
            case OM_CHILDADDED:
                $IPSObjekt = IPS_GetObject($Data[0]);
                if (($SenderID <> $this->InstanceID) or ( $IPSObjekt['ObjectType'] <> otLink))
                    return;
                $TriggerVarList = $this->NoTriggerVarList;
                $Index = $TriggerVarList->IndexOfLinkID($Data[0]);
                if ($Index !== false)
                    return;
                $this->RegisterLinkWatch($Data[0]);
                // Prüfen ob Ziel eigener State ist
                $Link = IPS_GetLink($Data[0]);
                if ($this->ReadPropertyBoolean('HasState'))
                {
                    if ($this->GetIDForIdent('STATE') == $Link['TargetID'])
                    {
                        $this->SetStatus(204); //State ist in den Links
                        $this->StopTimer();
                        return;
                    }
                }
                if ($Link['TargetID'] > 0)
                {
                    $Target = IPS_GetObject($Link['TargetID']);
                    if ($Target['ObjectType'] != otVariable)
                        return;
                }
                if (count($TriggerVarList->Items) == 0)
                    $this->SetStatus(IS_ACTIVE);
                $NoTriggerVar = new TNoTriggerVar($Link['TargetID'], $Data[0], false);
                $TriggerVarList->Add($NoTriggerVar);
                $this->NoTriggerVarList = $TriggerVarList;
                $this->RegisterVariableWatch($Link['TargetID']);
                $this->StartTimer();         // neue Var für timer festlegen und timer starten
                break;
            case OM_CHILDREMOVED:
                $TriggerVarList = $this->NoTriggerVarList;

                $Index = $TriggerVarList->IndexOfLinkID($Data[0]);
                $this->UnregisterLinkWatch($Data[0]);
                if ($Index === false)
                    return;
                $NoTriggerVar = $TriggerVarList->Get($Index);
                $this->UnregisterVariableWatch($NoTriggerVar->VarId);
                //und jetzt gleich prüfen ob vorher alarm und merken
                if ($NoTriggerVar->Alert)
                {
                    $this->Alerts--;
                    if ($this->Alerts == 0) //war letzter Alarm dann ruhe senden uns status setzen
                    {
                        $this->DoScript($NoTriggerVar->VarId, false, true);
                        $this->SetStateVar(false, $NoTriggerVar->VarId);
                    }
                    else
                    { // noch mehr in Alarm, also kein state setzen und...
                        if ($this->ReadPropertyBoolean('MultipleAlert')) //bei mehrfach auch ruhe sende
                        {
                            $this->DoScript($NoTriggerVar->VarId, false, true);
                        }
                    }
                }
                $TriggerVarList->Remove($Index);
                $this->NoTriggerVarList = $TriggerVarList;
                if (count($TriggerVarList->Items) == 0)
                {
                    $this->ActiveVarID = 0;
                    $this->SetStatus(203);
                    $this->StopTimer();
                    return;
                }
                $ActiveVarID = $this->ActiveVarID;
                if (($NoTriggerVar->VarId == $ActiveVarID) or ( $ActiveVarID == 0))
                {
                    $this->StartTimer();         // neue Var für timer festlegen und timer starten
                }

                break;
        }
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function ApplyChanges()
    {
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
        parent::ApplyChanges();
        $this->RegisterTimer('NoTrigger', 0, '<? NT_TimerFire2(' . $this->InstanceID . '); ');

        if ($this->ReadPropertyBoolean('HasState'))
            $this->MaintainVariable('STATE', 'STATE', vtBoolean, '~Alert', 0, true);
        else
            $this->MaintainVariable('STATE', 'STATE', vtBoolean, '~Alert', 0, false);
        if (IPS_GetKernelRunlevel() != KR_READY)
            return;
        $this->RegisterMessage($this->InstanceID, OM_CHILDADDED);
        $this->RegisterMessage($this->InstanceID, OM_CHILDREMOVED);
        if ($this->CheckConfig())
            $this->StartTimer();
    }

################## PRIVATE     

    /**
     * Prüft die Konfiguration
     * 
     * @access private
     * @return  boolean True bei OK
     */
    private function CheckConfig()
    {
        $temp = true;
        if ($this->ReadPropertyBoolean('Active') == true)
        {
            if ($this->ReadPropertyInteger('Timer') < 1)
            {
                $this->SetStatus(202); //Error Timer is Zero
                $temp = false;
            }
            if (count($this->NoTriggerVarList->Items) == 0)
            {
                $this->SetStatus(203); // kein Childs
                $temp = false;
            }
            else
            {
                if ($this->ReadPropertyBoolean('HasState'))
                {
                    if (array_key_exists($this->GetIDForIdent('STATE'), $this->NoTriggerVarList))
                    {
                        $this->SetStatus(204); //State ist in den Links
                        $temp = false;
                    }
                }
            }
            if ($temp)
            {
                $this->SetStatus(IS_ACTIVE);
            }
        }
        else
        {
            $temp = false;
            $this->SetStatus(IS_INACTIVE);
        }
        return $temp;
    }

    /**
     * Startet den Timer bis zum Alarm
     *
     * @access private
     */
    private function StartTimer()
    {
        if (IPS_GetKernelRunlevel() != KR_READY)
            return;
        $this->ActiveVarID = 0;
        $NowTime = time();
        $LastTime = $NowTime + 98765; //init wert damit lasttime immer größer als aktuelle zeit
        $TriggerVarList = $this->NoTriggerVarList;
        foreach ($TriggerVarList->Items as $i => $IPSVars)
        {
            if (!IPS_VariableExists($IPSVars->VarId))
                continue;
            if ($IPSVars->Alert === true)
                continue;
            $Variable = IPS_GetVariable($IPSVars->VarId);
            $TestTime = $Variable['VariableUpdated'];

            if (($TestTime + $this->ReadPropertyInteger('Timer')) < $NowTime) //alarm da in vergangenheit
            {
                $TriggerVarList->Items[$i]->Alert = true;
                $this->Alerts++;
                if ($this->Alerts == 1)
                {
                    $this->SetStateVar(true, $IPSVars->VarId);
                    $this->DoScript($IPSVars->VarId, true, false);
                }
                else
                {
                    if ($this->ReadPropertyBoolean('MultipleAlert'))
                        $this->DoScript($IPSVars->VarId, true, false);
                }
                continue;
            } else
            {
                if ($TestTime < $LastTime)
                {
                    $LastTime = $TestTime;
                    $this->ActiveVarID = $IPSVars->VarId;
                }
            }
        }
        $this->NoTriggerVarList = $TriggerVarList;


        if ($this->ActiveVarID == 0)
        {
            IPS_LogMessage('NoTrigger', 'Keine Var mehr in Ruhe. Überwachung pausiert');
            $this->StopTimer();
        }
        else
        {
            $TargetTime = $LastTime + $this->ReadPropertyInteger('Timer');
            $DiffTime = $TargetTime - $NowTime;
            $this->SetTimerInterval('NoTrigger', $DiffTime * 1000);
        }
    }

    /**
     * Stopt den Timer
     *
     * @access private
     */
    private function StopTimer()
    {
        $this->SetTimerInterval('NoTrigger', 0);
    }

    /**
     * Timer abelaufen Alarm wird erzeugt.
     *
     * @access public
     */
    public function TimerFire2()
    {
        $this->StopTimer();
        if (IPS_GetKernelRunlevel() != KR_READY)
            return;
        if ($this->Alerts == 0)
        {
            $this->SetStateVar(true, $this->ActiveVarID);
            $this->DoScript($this->ActiveVarID, true, false);
        }
        else
        {
            if ($this->ReadPropertyBoolean('MultipleAlert'))
                $this->DoScript($this->ActiveVarID, true, false);
        }
        $this->Alerts++;
        $TriggerVarList = $this->NoTriggerVarList;
        foreach ($TriggerVarList->Items as $i => $IPSVars)
        {
            if ($IPSVars->VarId == $this->ActiveVarID)
                $TriggerVarList->Items[$i]->Alert = true;
        }
        $this->NoTriggerVarList = $TriggerVarList;
        $this->StartTimer();
    }

    private function GetAllTargets()
    {
        $Links = IPS_GetChildrenIDs($this->InstanceID);
        foreach ($this->NoTriggerVarList->Items as $IPSVar)
        {
            $this->UnregisterVariableWatch($IPSVar->VarId);
            $this->UnregisterLinkWatch($IPSVar->LinkId);
        }
        $TriggerVarList = new TNoTriggerVarList();

        foreach ($Links as $Link)
        {
            $Objekt = IPS_GetObject($Link);
            if ($Objekt['ObjectType'] != otLink)
                continue;

            $Target = @IPS_GetObject(IPS_GetLink($Link)['TargetID']);
            if ($Target === false)
                continue;
            if ($Target['ObjectType'] != otVariable)
                continue;
//      zur Liste hinzufügen und Register auf Variable, Link etc...            
            $NoTriggerVar = new TNoTriggerVar($Target['ObjectID'], $Link, false);
            $TriggerVarList->Add($NoTriggerVar);
        }
        $this->NoTriggerVarList = $TriggerVarList;
        foreach ($TriggerVarList->Items as $IPSVar)
        {
            $this->RegisterVariableWatch($IPSVar->VarId);
            $this->RegisterLinkWatch($IPSVar->LinkId);
        }
    }

}

/** @} */