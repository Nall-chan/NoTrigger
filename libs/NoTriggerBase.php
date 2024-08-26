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

eval('declare(strict_types=1);namespace NoTrigger {?>' . file_get_contents(__DIR__ . '/helper/VariableHelper.php') . '}');
eval('declare(strict_types=1);namespace NoTrigger {?>' . file_get_contents(__DIR__ . '/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace NoTrigger {?>' . file_get_contents(__DIR__ . '/helper/DebugHelper.php') . '}');

/**
 * NoTrigger Basis-Klasse für die die Überwachung von Variablen auf fehlende Änderung/Aktualisierung.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2022 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       2.80
 *
 * @example <b>Ohne</b>
 *
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 */
class NoTriggerBase extends IPSModuleStrict
{
    use \NoTrigger\VariableHelper;
    use \NoTrigger\BufferHelper;
    use \NoTrigger\DebugHelper;
    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyBoolean('Active', false);
        $this->RegisterPropertyBoolean('MultipleAlert', false);
        $this->RegisterPropertyInteger('ScriptID', 1);
        $this->RegisterPropertyInteger('Timer', 0);
        $this->RegisterPropertyBoolean('HasState', true);
        $this->RegisterPropertyInteger('StartUp', 0);
        $this->RegisterPropertyInteger('CheckMode', 0);
        $this->RegisterPropertyString('Actions', json_encode([]));

        $this->RegisterTimer('NoTrigger', 0, 'NT_TimerFire($_IPS["TARGET"]); ');
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'RunActions':
                $this->RunActions(unserialize($Value));
                return;
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
    }

    public function Migrate(string $JSONData): string
    {
        $Data = json_decode($JSONData);
        if (property_exists($Data->configuration, 'ScriptID')) {
            $ScriptID = $Data->configuration->ScriptID;
            if (IPS_ScriptExists($ScriptID)) {
                $Action = [
                    'event'    => -1,
                    'condition'=> '',
                    'action'   => json_encode(
                        [
                            'actionID'  => '{7938A5A2-0981-5FE0-BE6C-8AA610D654EB}',
                            'parameters'=> [
                                'TARGET'     => $ScriptID,
                                'ENVIRONMENT'=> 'default',
                                'PARENT'     => 0
                            ]
                        ]
                    )
                ];
                $Data->configuration->Actions = json_encode([$Action]);
                $this->SendDebug('Migrate Action', $Action, 0);
                $this->LogMessage('Migrated Action:' . json_encode($Action), KL_MESSAGE);
            }
            if (IPS_GetInstance($this->InstanceID)['ModuleInfo']['ModuleID'] == '{28198BA1-3563-4C85-81AE-8176B53589B8}') {
                // Müssen bei Group noch die Links konvertiert werden?
                if (IPS_GetProperty($this->InstanceID, 'Variables') == '[]') {
                    $Variables = [];
                    $Links = IPS_GetChildrenIDs($this->InstanceID);
                    foreach ($Links as $Link) {
                        $Objekt = IPS_GetObject($Link);
                        if ($Objekt['ObjectType'] != OBJECTTYPE_LINK) {
                            continue;
                        }
                        $Target = @IPS_GetObject(IPS_GetLink($Link)['TargetID']);
                        if ($Target === false) {
                            continue;
                        }
                        if ($Target['ObjectType'] != OBJECTTYPE_VARIABLE) {
                            continue;
                        }
                        if (!in_array($Target['ObjectID'], $Variables)) {
                            //zur Liste hinzufügen
                            $Variables[] = ['VariableID'=> $Target['ObjectID']];
                        }
                        $this->SendDebug('Migrate Link', $Link, 0);
                        $this->LogMessage('Migrated Link:' . $Link, KL_MESSAGE);
                        $this->SendDebug('Migrate Variable', $Target['ObjectID'], 0);
                        $this->LogMessage('Migrated Variable:' . $Target['ObjectID'], KL_MESSAGE);
                        IPS_DeleteLink($Link);
                    }
                    $Data->configuration->Variables = json_encode($Variables);
                }
            }
            $this->SendDebug('Migrate', json_encode($Data), 0);
            $this->LogMessage('Migrated settings:' . json_encode($Data), KL_MESSAGE);
        }
        $Actions = json_decode($Data->configuration->Actions, true);
        foreach ($Actions as &$Action) {
            $Action['condition'] = $Action['condition'] ?? '[]';
        }
        $Data->configuration->Actions = json_encode($Actions);
        return json_encode($Data);
    }

    /**
     * Setzt die Status-Variable.
     *
     * @param bool $NewState Der neue Wert der Statusvariable
     */
    protected function SetStateVar(bool $NewState): void
    {
        if ($this->ReadPropertyBoolean('HasState')) {
            if (!IPS_VariableExists($this->FindIDForIdent('STATE'))) {
                $this->MaintainVariable('STATE', 'STATE', VARIABLETYPE_BOOLEAN, '~Alert', 0, true);
            }
            $this->SetValue('STATE', $NewState);
        }
    }

    /**
     * Desregistriert eine Überwachung einer Variable.
     *
     * @param int $VarId IPS-ID der Variable.
     */
    protected function UnregisterVariableWatch($VarId): void
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
    protected function RegisterVariableWatch(int $VarId): void
    {
        if ($VarId == 0) {
            return;
        }
        $this->SendDebug('RegisterVM', $VarId, 0);
        $this->RegisterMessage($VarId, VM_DELETE);
        $this->RegisterMessage($VarId, VM_UPDATE);
        $this->RegisterReference($VarId);
    }

    protected function DoAction(int $IPSVarID, bool $NewState, bool $OldState): void
    {
        $AlarmData['VALUE'] = $NewState;
        $AlarmData['OLDVALUE'] = $OldState;
        $AlarmData['VARIABLE'] = $IPSVarID;
        $AlarmData['SENDER'] = 'NoTrigger';
        $AlarmData['PARENT'] = $this->InstanceID;
        $AlarmData['EVENT'] = $this->InstanceID;
        IPS_RunScriptText('IPS_RequestAction(' . $this->InstanceID . ',\'RunActions\',\'' . serialize($AlarmData) . '\');');
    }

    protected function RunActions(array $AlarmData): void
    {
        $Actions = json_decode($this->ReadPropertyString('Actions'), true);
        if (count($Actions) == 0) {
            return;
        }
        $RunActions = array_filter($Actions, function ($Action) use ($AlarmData)
        {
            if (!IPS_IsConditionPassing($Action['condition'])) {
                $this->SendDebug('Condition', 'Condition is blocking', 0);
                return false;
            }
            if ($Action['event'] == -1) {
                return true;
            }
            return (bool) $Action['event'] === $AlarmData['VALUE'];
        });
        $this->SendDebug('RunActions', $RunActions, 0);
        if (count($RunActions)) {
            $this->SendDebug('RunActions', $AlarmData, 0);
        }
        foreach ($RunActions as $Action) {
            $ActionData = json_decode($Action['action'], true);
            $ActionData['parameters'] = array_merge($ActionData['parameters'], $AlarmData);
            //$this->SendDebug('ActionData', $ActionData, 0);
            IPS_RunAction($ActionData['actionID'], $ActionData['parameters']);
        }
    }
}

/* @} */
