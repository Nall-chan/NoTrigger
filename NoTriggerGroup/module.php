<?php

declare(strict_types=1);
/*
 * @addtogroup notrigger
 * @{
 *
 * @package       NoTrigger
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2022 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.72
 *
 */
eval('declare(strict_types=1);namespace NoTrigger {?>' . file_get_contents(__DIR__ . '/../libs/helper/SemaphoreHelper.php') . '}');
require_once __DIR__ . '/../libs/NoTriggerBase.php';

/**
 * TNoTriggerVar ist eine Klasse welche die Daten einer überwachten Variable enthält.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2022 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       2.72
 *
 * @example <b>Ohne</b>
 */
class TNoTriggerVar
{
    /**
     * IPS-ID der Variable.
     *
     * @var int
     */
    public $VarId = 0;

    /**
     * True wenn Variable schon Alarm ausgelöst hat.
     *
     * @var bool
     */
    public $Alert = false;

    /**
     * Erzeugt ein neues Objekt aus TNoTriggerVar.
     *
     * @param int $VarId  IPS-ID der Variable.
     * @param int $Alert  Wert für Alert
     *
     * @return TNoTriggerVar Das erzeugte Objekt.
     */
    public function __construct(int $VarId, bool $Alert)
    {
        $this->VarId = $VarId;
        $this->Alert = $Alert;
    }
}

/**
 * TNoTriggerVarList ist eine Klasse welche die Daten aller überwachten Variablen enthält.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2022 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       2.72
 *
 * @example <b>Ohne</b>
 */
class TNoTriggerVarList
{
    /**
     * Array mit allen überwachten Variablen.
     *
     * @var array
     */
    public $Items = [];

    /**
     * Liefert die Daten welche behalten werden müssen.
     */
    public function __sleep()
    {
        return ['Items'];
    }

    /**
     * Fügt einen Eintrag in $Items hinzu.
     *
     * @param TNoTriggerVar $NoTriggerVar Das hinzuzufügende Variablen-Objekt.
     */
    public function Add(TNoTriggerVar $NoTriggerVar)
    {
        $this->Items[] = $NoTriggerVar;
    }

    /**
     * Löscht einen Eintrag aus $Items.
     *
     * @param int $Index Der Index des zu löschenden Items.
     */
    public function Remove(int $Index)
    {
        unset($this->Items[$Index]);
    }

    /**
     * Liefert einen bestimmten Eintrag aus den Items.
     *
     * @param int $Index
     *
     * @return TNoTriggerVar
     */
    public function Get(int $Index)
    {
        return $this->Items[$Index];
    }

    /**
     * Liefert den Index von dem Item mit der entsprechenden IPS-Variablen-ID.
     *
     * @param int $VarId Die zu suchende IPS-ID der Variable.
     *
     * @return int Index des gefundenen Eintrags.
     */
    public function IndexOfVarID(int $VarId)
    {
        foreach ($this->Items as $Index => $NoTriggerVar) {
            if ($NoTriggerVar->VarId == $VarId) {
                return $Index;
            }
        }
        return false;
    }
}

/**
 * NoTrigger Klasse für die die Überwachung von mehreren Variablen auf fehlende Änderung/Aktualisierung.
 * Erweitert NoTriggerBase.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2022 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       2.72
 *
 * @example <b>Ohne</b>
 *
 * @property int $Alerts Anzahl der Alarme
 * @property int $ActiveVarID Anzahl der aktiven Vars
 * @property TNoTriggerVarList $NoTriggerVarList Liste mit allen Variablen
 * @method bool lock(string $ident)
 * @method void unlock(string $ident)
 */
class NoTriggerGroup extends NoTriggerBase
{
    use \NoTrigger\Semaphore;

    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('Active', false);
        $this->RegisterPropertyBoolean('MultipleAlert', false);
        $this->RegisterPropertyInteger('ScriptID', 1);
        $this->RegisterPropertyInteger('Timer', 0);
        $this->RegisterPropertyBoolean('HasState', true);
        $this->RegisterPropertyInteger('StartUp', 0);
        $this->RegisterPropertyInteger('CheckMode', 0);
        $this->RegisterPropertyString('Variables', json_encode([]));
        $this->RegisterPropertyString('Actions', json_encode([]));
        $this->Alerts = 0;
        $this->ActiveVarID = 0;
        $this->NoTriggerVarList = new TNoTriggerVarList();
        $this->RegisterTimer('NoTrigger', 0, 'NT_TimerFire($_IPS["TARGET"]); ');
    }

    /**
     * Interne Funktion des SDK.
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->GetAllTargets();
                switch ($this->ReadPropertyInteger('StartUp')) {
                    case 0:
                        if ($this->CheckConfig()) {
                            $this->StartTimer();
                        }
                        break;
                    case 1:
                        if ($this->CheckConfig()) {
                            $this->SetTimerInterval('NoTrigger', $this->ReadPropertyInteger('Timer') * 1000);
                        }
                        break;
                }
                $this->UnregisterMessage(0, IPS_KERNELSTARTED);
                break;
            case VM_UPDATE:
                // prüfen ob in liste und auswerten wegen Ruhemeldung wenn Alarm war
                $this->lock('NoTriggerVarList');
                $TriggerVarList = $this->NoTriggerVarList;
                $Index = $TriggerVarList->IndexOfVarID($SenderID);
                if ($Index === false) {
                    $this->unlock('NoTriggerVarList');
                    return;
                }
                $NoTriggerVar = $TriggerVarList->Get($Index);
                if ($NoTriggerVar->Alert && (($Data[1] == true) || ($this->ReadPropertyInteger('CheckMode') == 0))) {
                    $TriggerVarList->Items[$Index]->Alert = false;
                    $this->NoTriggerVarList = $TriggerVarList;
                    $this->Alerts--;
                    if ($this->Alerts == 0) { //war letzter Alarm dann ruhe senden und status setzen
                        $this->SetStateVar(false);
                        $this->DoAction($NoTriggerVar->VarId, false, true);
                    } else { // noch mehr in Alarm, also kein state setzen und...
                        if ($this->ReadPropertyBoolean('MultipleAlert')) { //bei mehrfach auch ruhe sende
                            $this->DoAction($NoTriggerVar->VarId, false, true);
                        }
                    }
                }
                $this->unlock('NoTriggerVarList');
                // Var das die aktive Var für den Timer ? Dann Timer neu berechnen
                $ActiveVarID = $this->ActiveVarID;
                if ((($SenderID == $ActiveVarID) || ($ActiveVarID == 0)) && (($Data[1] === true) || ($this->ReadPropertyInteger('CheckMode') == 0))) { //Update von Var welche gerade den Timer steuert
                    $this->StartTimer();         // neue Var für timer festlegen und timer starten
                }
                break;
            case VM_DELETE:
                $this->UnregisterVariableWatch($SenderID);
                // prüfen ob in liste und auswerten wegen Ruhemeldung wenn Alarm war
                $this->lock('NoTriggerVarList');
                $TriggerVarList = $this->NoTriggerVarList;
                $Index = $TriggerVarList->IndexOfVarID($SenderID);
                if ($Index === false) {
                    $this->unlock('NoTriggerVarList');
                    return;
                }
                $NoTriggerVar = $TriggerVarList->Get($Index);

                //und jetzt gleich prüfen ob vorher alarm und merken
                if ($NoTriggerVar->Alert) {
                    $this->Alerts--;
                    if ($this->Alerts == 0) { //war letzter Alarm dann ruhe senden uns status setzen
                        $this->SetStateVar(false);
                        $this->DoAction($NoTriggerVar->VarId, false, true);
                    } else { // noch mehr in Alarm, also kein state setzen und...
                        if ($this->ReadPropertyBoolean('MultipleAlert')) { //bei mehrfach auch ruhe sende
                            $this->DoAction($NoTriggerVar->VarId, false, true);
                        }
                    }
                }
                $TriggerVarList->Remove($Index);
                $this->NoTriggerVarList = $TriggerVarList;
                $this->unlock('NoTriggerVarList');
                if (count($TriggerVarList->Items) == 0) {
                    $this->ActiveVarID = 0;
                    $this->SetStatus(IS_EBASE + 3);
                    $this->StopTimer();
                    return;
                }
                $ActiveVarID = $this->ActiveVarID;
                if (($SenderID == $ActiveVarID) || ($ActiveVarID == 0)) {
                    $this->StartTimer();         // neue Var für timer festlegen und timer starten
                }
                break;
        }
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        $this->RegisterMessage($this->InstanceID, OM_CHILDADDED);
        $this->RegisterMessage($this->InstanceID, OM_CHILDREMOVED);
        parent::ApplyChanges();

        $this->MaintainVariable('STATE', 'STATE', VARIABLETYPE_BOOLEAN, '~Alert', 0, $this->ReadPropertyBoolean('HasState'));
        if (IPS_GetKernelRunlevel() != KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }
        if ($this->ConfigHasUpgraded()) {
            return;
        }
        $this->GetAllTargets();
        if ($this->CheckConfig()) {
            $this->StartTimer();
        } else {
            $this->StopTimer();
        }
    }

    /**
     * Timer abgelaufen Alarm wird erzeugt.
     */
    public function TimerFire()
    {
        $this->StopTimer();
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if ($this->Alerts == 0) {
            $this->SetStateVar(true);
            $this->DoAction($this->ActiveVarID, true, false);
        } else {
            if ($this->ReadPropertyBoolean('MultipleAlert')) {
                $this->DoAction($this->ActiveVarID, true, false);
            }
        }
        $this->lock('NoTriggerVarList');
        $this->Alerts++;
        $TriggerVarList = $this->NoTriggerVarList;
        foreach ($TriggerVarList->Items as $i => $IPSVars) {
            if ($IPSVars->VarId == $this->ActiveVarID) {
                $TriggerVarList->Items[$i]->Alert = true;
            }
        }
        $this->NoTriggerVarList = $TriggerVarList;
        $this->unlock('NoTriggerVarList');
        $this->StartTimer();
    }

    //################# PRIVATE

    /**
     * Prüft die Konfiguration.
     *
     * @return bool True bei OK
     */
    private function CheckConfig()
    {
        $Result = true;
        if ($this->ReadPropertyBoolean('Active')) {
            if ($this->ReadPropertyInteger('Timer') < 1) {
                $this->SetStatus(IS_EBASE + 2); //Error Timer is Zero
                $Result = false;
            }
            if (count($this->NoTriggerVarList->Items) == 0) {
                $this->SetStatus(IS_EBASE + 3); // kein Variablen in der Liste
                $Result = false;
            } else {
                if ($this->ReadPropertyBoolean('HasState')) {
                    if ($this->NoTriggerVarList->IndexOfVarID($this->GetIDForIdent('STATE'))) {
                        $this->SetStatus(IS_EBASE + 4); //State ist in der Liste
                        $Result = false;
                    }
                }
            }
            if ($Result) {
                $this->SetStatus(IS_ACTIVE);
            }
        } else {
            $Result = false;
            $this->SetStatus(IS_INACTIVE);
        }
        return $Result;
    }

    /**
     * Startet den Timer bis zum Alarm.
     */
    private function StartTimer()
    {
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->ActiveVarID = 0;
        $NowTime = time();
        $LastTime = $NowTime + 98765; //init wert damit lasttime immer größer als aktuelle zeit
        $this->lock('NoTriggerVarList');
        $TriggerVarList = $this->NoTriggerVarList;
        foreach ($TriggerVarList->Items as $i => $IPSVars) {
            if (!IPS_VariableExists($IPSVars->VarId)) {
                continue;
            }
            if ($IPSVars->Alert === true) {
                continue;
            }
            $Variable = IPS_GetVariable($IPSVars->VarId);
            $TestTime = $Variable['VariableUpdated'];

            if (($TestTime + $this->ReadPropertyInteger('Timer')) < $NowTime) { //alarm da in vergangenheit
                $TriggerVarList->Items[$i]->Alert = true;

                if ($this->Alerts == 0) {
                    $this->SetStateVar(true);
                    $this->DoAction($IPSVars->VarId, true, false);
                } else {
                    if ($this->ReadPropertyBoolean('MultipleAlert')) {
                        $this->DoAction($IPSVars->VarId, true, false);
                    }
                }
                $this->Alerts++;
            } else {
                if ($TestTime < $LastTime) {
                    $LastTime = $TestTime;
                    $this->ActiveVarID = $IPSVars->VarId;
                }
            }
        }
        $this->NoTriggerVarList = $TriggerVarList;
        $this->unlock('NoTriggerVarList');
        if ($this->ActiveVarID == 0) {
            $this->LogMessage($this->Translate('All alarms have fired. Monitoring paused.'), KL_NOTIFY);
            $this->StopTimer();
        } else {
            $TargetTime = $LastTime + $this->ReadPropertyInteger('Timer');
            $DiffTime = $TargetTime - $NowTime;
            $this->SetTimerInterval('NoTrigger', $DiffTime * 1000);
        }
    }

    /**
     * Stopt den Timer.
     */
    private function StopTimer()
    {
        $this->SetTimerInterval('NoTrigger', 0);
    }

    /**
     * Liest alle zu Überwachenden Variablen ein.
     */
    private function GetAllTargets()
    {
        $this->lock('NoTriggerVarList');
        $OldTriggerVarList = $this->NoTriggerVarList;
        foreach ($OldTriggerVarList->Items as $IPSVar) {
            $this->UnregisterVariableWatch($IPSVar->VarId);
        }
        $this->unlock('NoTriggerVarList');
        if (!$this->ReadPropertyBoolean('Active')) {
            return;
        }
        $this->lock('NoTriggerVarList');
        $NewVariables = json_decode($this->ReadPropertyString('Variables'), true);
        $NewTriggerVarList = new TNoTriggerVarList();
        foreach ($NewVariables as $NewVariable) {
            $Objekt = @IPS_GetObject($NewVariable['VariableID']);
            if ($Objekt == 0) {
                continue;
            }
            if ($Objekt['ObjectType'] != OBJECTTYPE_VARIABLE) {
                continue;
            }
            $NewAlertState = false;
            //in der alten Liste prüfen ob hier noch ein Alarm war.
            $OldVariableIndex = $OldTriggerVarList->IndexOfVarID($NewVariable['VariableID']);
            if ($OldVariableIndex !== false) {
                $this->SendDebug('keepState (' . $OldVariableIndex . ')', $NewVariable['VariableID'], 0);
                $NewAlertState = $OldTriggerVarList->Get($OldVariableIndex)->Alert;
                $OldTriggerVarList->Remove($OldVariableIndex);
            }
            //zur Liste hinzufügen und Register auf Variable
            $NewTriggerVar = new TNoTriggerVar($NewVariable['VariableID'], $NewAlertState);
            $NewTriggerVarList->Add($NewTriggerVar);
            $this->RegisterVariableWatch($NewVariable['VariableID']);
        }
        $this->NoTriggerVarList = $NewTriggerVarList;
        $this->unlock('NoTriggerVarList');
        $this->SendDebug('removeStates', $OldTriggerVarList->Items, 0);
        foreach ($OldTriggerVarList->Items as $OldTriggerVar) {
            if ($OldTriggerVar->Alert) {
                $this->Alerts--;
                if ($this->Alerts == 0) { //war letzter Alarm dann ruhe senden uns status setzen
                    $this->SetStateVar(false);
                    $this->DoAction($OldTriggerVar->VarId, false, true);
                } else { // noch mehr in Alarm, also kein state setzen und...
                    if ($this->ReadPropertyBoolean('MultipleAlert')) { //bei mehrfach auch ruhe sende
                        $this->DoAction($OldTriggerVar->VarId, false, true);
                    }
                }
            }
        }
    }
}

/* @} */
