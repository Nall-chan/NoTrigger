<?php

declare(strict_types=1);
/*
 * @addtogroup notrigger
 * @{
 *
 * @package       NoTrigger
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2020 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.60
 *
 */

eval('declare(strict_types=1);namespace NoTrigger {?>' . file_get_contents(__DIR__ . '/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace NoTrigger {?>' . file_get_contents(__DIR__ . '/helper/DebugHelper.php') . '}');

/**
 * NoTrigger Basis-Klasse für die die Überwachung von Variablen auf fehlende Änderung/Aktualisierung.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2020 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       2.60
 *
 * @example <b>Ohne</b>
 */
class NoTriggerBase extends IPSModule
{
    use \NoTrigger\BufferHelper,
        \NoTrigger\DebugHelper;

    /**
     * Setzt die Status-Variable.
     *
     * @param bool $NewState Der neue Wert der Statusvariable
     */
    protected function SetStateVar(bool $NewState)
    {
        if ($this->ReadPropertyBoolean('HasState')) {
            if (!IPS_VariableExists(@$this->GetIDForIdent('STATE'))) {
                $this->MaintainVariable('STATE', 'STATE', VARIABLETYPE_BOOLEAN, '~Alert', 0, true);
            }
            SetValueBoolean($this->GetIDForIdent('STATE'), $NewState);
        }
    }

    /**
     * Startet das Alarm-Script.
     *
     * @param int  $IPSVarID Variable welche den Alarm ausgelöst hat.
     * @param bool $NewState Alarmstatus neu
     * @param bool $OldState Alarmstatus vorher
     */
    protected function DoScript(int $IPSVarID, bool $NewState, bool $OldState)
    {
        if ($this->ReadPropertyInteger('ScriptID') != 0) {
            if (IPS_ScriptExists($this->ReadPropertyInteger('ScriptID'))) {
                IPS_RunScriptEx($this->ReadPropertyInteger('ScriptID'), [
                    'VALUE'    => $NewState,
                    'OLDVALUE' => $OldState,
                    'VARIABLE' => $IPSVarID,
                    'EVENT'    => $this->InstanceID,
                    'SENDER'   => 'NoTrigger'
                        ]
                );
            } else {
                $this->LogMessage(sprintf($this->Translate('Script %d not exists!'), $this->ReadPropertyInteger('ScriptID')), KL_ERROR);
            }
        }
    }

    /**
     * Desregistriert eine Überwachung eines Links.
     *
     * @param int $LinkId IPS-ID des Link.
     */
    protected function UnregisterLinkWatch(int $LinkId)
    {
        if ($LinkId == 0) {
            return;
        }

        $this->SendDebug('UnregisterLM', $LinkId, 0);
        $this->UnregisterMessage($LinkId, LM_CHANGETARGET);
        $this->UnregisterReference($LinkId);
    }

    /**
     * Registriert eine Überwachung eines Links.
     *
     * @param int $LinkId IPS-ID des Link.
     */
    protected function RegisterLinkWatch(int $LinkId)
    {
        if ($LinkId == 0) {
            return;
        }
        $this->SendDebug('RegisterLM', $LinkId, 0);
        $this->RegisterMessage($LinkId, LM_CHANGETARGET);
        $this->RegisterReference($LinkId);
    }

    /**
     * Desregistriert eine Überwachung einer Variable.
     *
     * @param int $VarId IPS-ID der Variable.
     */
    protected function UnregisterVariableWatch($VarId)
    {
        if ($VarId == 0) {
            return;
        }

        $this->SendDebug('UnregisterVM', $VarId, 0);
        $this->UnregisterMessage($VarId, VM_DELETE);
        $this->UnregisterMessage($VarId, VM_UPDATE);
        $this->UnregisterReference($VarId);
    }

    /**
     * Registriert eine Überwachung einer Variable.
     *
     * @param int $VarId IPS-ID der Variable.
     */
    protected function RegisterVariableWatch(int $VarId)
    {
        if ($VarId == 0) {
            return;
        }
        $this->SendDebug('RegisterVM', $VarId, 0);
        $this->RegisterMessage($VarId, VM_DELETE);
        $this->RegisterMessage($VarId, VM_UPDATE);
        $this->RegisterReference($VarId);
    }
}

/* @} */
