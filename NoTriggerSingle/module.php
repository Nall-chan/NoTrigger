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
 * @version       2.80
 *
 */

require_once __DIR__ . '/../libs/NoTriggerBase.php';

/**
 * NoTrigger Klasse für die die Überwachung einer Variable auf fehlende Änderung/Aktualisierung.
 * Erweitert NoTriggerBase.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2022 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       2.80
 *
 * @example <b>Ohne</b>
 *
 * @property bool $State Letzter Zustand
 * @property int $VarId ID der überwachten Variable
 */
class NoTriggerSingle extends NoTriggerBase
{
    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyInteger('VarID', 1);
        $this->State = false;
        $this->VarId = 0;
    }

    /**
     * Interne Funktion des SDK.
     */
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        switch ($Message) {
            case IPS_KERNELSTARTED:
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
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->MaintainVariable('STATE', 'STATE', VARIABLETYPE_BOOLEAN, '~Alert', 0, $this->ReadPropertyBoolean('HasState'));
        if (IPS_GetKernelRunlevel() != KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }
        if ($this->CheckConfig()) {
            $this->StartTimer();
        } else {
            $this->StopTimer();
        }
    }

    /**
     * Timer abgelaufen Alarm wird erzeugt.
     */
    public function TimerFire(): void
    {
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->SetStateVar(true);
            $this->DoAction($this->ReadPropertyInteger('VarID'), true, $this->State);
            $this->State = true;

            if ($this->ReadPropertyBoolean('MultipleAlert') == false) {
                $this->StopTimer();
            }  //kein Mehrfachalarm -> Timer aus
            else {
                $this->SetTimerInterval('NoTrigger', $this->ReadPropertyInteger('Timer') * 1000);
            }  // neuer Timer mit max. Zeit, ohne now zu berücksichtigen.
        }
    }

    //################# PRIVATE
    /**
     * Prüft die Konfiguration.
     *
     * @return bool True bei OK
     */
    private function CheckConfig(): bool
    {
        $this->UnregisterVariableWatch($this->VarId);
        $this->VarId = 0;
        $temp = true;
        if ($this->ReadPropertyBoolean('Active') == true) {
            if ($this->ReadPropertyInteger('Timer') < 1) {
                $this->SetStatus(IS_EBASE + 2); //Error Timer is Zero
                $temp = false;
            }
            if ($this->ReadPropertyInteger('VarID') == 0) {
                $this->SetStatus(IS_EBASE + 3); //VarID is Zero
                $temp = false;
            }
            if ($this->ReadPropertyBoolean('HasState')) {
                if ($this->ReadPropertyInteger('VarID') == $this->FindIDForIdent('STATE')) {
                    $this->SetStatus(IS_EBASE + 4); //VarID is Self
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
     * Startet den Timer bis zum Alarm.
     */
    private function StartTimer(): void
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
            $this->DoAction($this->ReadPropertyInteger('VarID'), true, $this->State);
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
                $this->DoAction($this->ReadPropertyInteger('VarID'), false, $this->State);
                $this->State = false;
            }

            $this->SetTimerInterval('NoTrigger', $DiffTime * 1000);
        }
    }

    /**
     * Stopt den Timer.
     */
    private function StopTimer(): void
    {
        $this->SetTimerInterval('NoTrigger', 0);
    }
}

/* @} */
