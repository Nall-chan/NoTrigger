<?php

declare(strict_types=1);
/*
 * @addtogroup notrigger
 * @{
 *
 * @package       NoTrigger
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2019 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.5
 *
 */

require_once(__DIR__ . '/../libs/NoTriggerBase.php');

/**
 * NoTrigger Klasse für die die Überwachung einer Variable auf fehlende Änderung/Aktualisierung.
 * Erweitert NoTriggerBase.
 *
 * @package       NoTrigger
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2019 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.5
 * @example <b>Ohne</b>
 *
 * @property int $State Letzer Zustand
 * @property int $VarId ID der überwachten Variable
 */
class NoTriggerSingle extends NoTriggerBase
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
        $this->RegisterPropertyInteger('VarID', 0);
        $this->RegisterPropertyInteger('Timer', 0);
        $this->RegisterPropertyInteger('ScriptID', 0);
        $this->RegisterPropertyBoolean('HasState', true);
        $this->RegisterPropertyInteger('StartUp', 0);
        $this->RegisterPropertyInteger('CheckMode', 0);
        $this->RegisterTimer('NoTrigger', 0, 'NT_TimerFire($_IPS["TARGET"]); ');
        $this->State = false;
        $this->VarId = 0;
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case IPS_KERNELMESSAGE:
                switch ($Data[0]) {
                    case KR_READY:
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
                        $this->UnregisterMessage(0, IPS_KERNELMESSAGE);
                        break;
                }
                break;
            case VM_UPDATE:
                if ($SenderID != $this->ReadPropertyInteger('VarID')) {
                    break;
                }
                if ($this->ReadPropertyInteger('CheckMode') == 1) {
                    if ($Data[1] == true) {
                        $this->StartTimer();
                    }
                } else {
                    $this->StartTimer();
                }
                break;
            case VM_DELETE:
                if ($SenderID != $this->ReadPropertyInteger('VarID')) {
                    break;
                }
                $this->UnregisterVariableWatch($SenderID);
                $this->VarId = 0;
                IPS_SetProperty($this->InstanceID, 'VarID', 0);
                IPS_ApplyChanges($this->InstanceID);
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

        $this->MaintainVariable('STATE', 'STATE', VARIABLETYPE_BOOLEAN, '~Alert', 0, $this->ReadPropertyBoolean('HasState'));
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if ($this->CheckConfig()) {
            $this->StartTimer();
        }
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
        $this->UnregisterVariableWatch($this->VarId);
        $this->VarId = 0;
        $temp = true;
        if ($this->ReadPropertyBoolean('Active') == true) {
            if ($this->ReadPropertyInteger('Timer') < 1) {
                $this->SetStatus(202); //Error Timer is Zero
                $temp = false;
            }
            if ($this->ReadPropertyInteger('VarID') == 0) {
                $this->SetStatus(203); //VarID is Zero
                $temp = false;
            }
            if ($this->ReadPropertyBoolean('HasState')) {
                if ($this->ReadPropertyInteger('VarID') == $this->GetIDForIdent('STATE')) {
                    $this->SetStatus(204); //VarID is Self
                    $temp = false;
                }
            }
            if ($temp) {
                $this->SetStatus(IS_ACTIVE);
                $this->RegisterVariableWatch($this->ReadPropertyInteger('VarID'));
                $this->VarId = $this->ReadPropertyInteger('VarID');
            }
        } else {
            $this->SetStatus(IS_INACTIVE);
            $temp = false;
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
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        $NowTime = time();
        if (!IPS_VariableExists($this->ReadPropertyInteger('VarID'))) {
            IPS_SetProperty($this->InstanceID, 'VarID', 0);
            IPS_ApplyChanges($this->InstanceID);
            return;
        }
        $Variable = IPS_GetVariable($this->ReadPropertyInteger('VarID'));
        $LastTime = $Variable['VariableUpdated'];
        $TargetTime = $LastTime + $this->ReadPropertyInteger('Timer');
        $DiffTime = $TargetTime - $NowTime;
        if ($TargetTime < $NowTime) {
            $this->SetStateVar(true);
            $this->DoScript($this->ReadPropertyInteger('VarID'), true, $this->State);
            $this->State = true;
            if ($this->ReadPropertyBoolean('MultipleAlert') == false) {
                $this->StopTimer();
            }  //kein Mehrfachalarm -> Timer aus
            else {
                $this->SetTimerInterval('NoTrigger', $this->ReadPropertyInteger('Timer') * 1000);
            }  // neuer Timer mit max. Zeit, ohne now zu berücksichtigen.
        } else {
            $this->SetStateVar(false);
            if ($this->State) {
                $this->DoScript($this->ReadPropertyInteger('VarID'), false, $this->State);
                $this->State = false;
            }

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
     * Timer abgelaufen Alarm wird erzeugt.
     *
     * @access public
     */
    public function TimerFire()
    {
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->SetStateVar(true);

            $this->DoScript($this->ReadPropertyInteger('VarID'), true, $this->State);
            $this->State = true;

            if ($this->ReadPropertyBoolean('MultipleAlert') == false) {
                $this->StopTimer();
            }  //kein Mehrfachalarm -> Timer aus
            else {
                $this->SetTimerInterval('NoTrigger', $this->ReadPropertyInteger('Timer') * 1000);
            }  // neuer Timer mit max. Zeit, ohne now zu berücksichtigen.
        }
    }

}

/** @} */
