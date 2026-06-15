<?php

declare(strict_types=1);

class RoomShadowingController extends IPSModule {
    
    public function Create() {
        parent::Create();
        
        //Properties
        $this->RegisterPropertyInteger('InputTemperatureCurrentVariable', 0);
        $this->RegisterPropertyInteger('InputTemperatureTargetVariable', 0);
        $this->RegisterPropertyInteger('GlobalShadowingStatusVariable', 0);
        //$this->RegisterPropertyBoolean('EnableRoomShadowingByTemperature', true);
       
        $this->RegisterPropertyFloat('ThresholdTemperature', 10);
        $this->RegisterPropertyInteger('InputOutdoorTemperature', 0);

        //Variables
        $ActiveOptions = json_encode([
            [
                'Value' => true,
                'Caption' => 'Automatik',
                'IconActive' => false,
                'Icon' => '',
                'Color' => 0x00ff00
            ],[
                'Value' => false,
                'Caption' => 'Deaktiviert',
                'IconActive' => false,
                'Icon' => '',
                'Color' => 0xff0000
            ]
        ]);    
        $this->RegisterVariableBoolean('Active', 'Raum Beschattung aktiv', ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'ICON' => 'power-off', 'OPTIONS' => $ActiveOptions]);
        $this->EnableAction('Active');

        $this->RegisterVariableBoolean('ColdShadowing', 'Beschattung bei Kälte', ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'ICON' => 'snowflake', 'OPTIONS' => $ActiveOptions]);
        $this->EnableAction('ColdShadowing');
        //$this->SetValue("ColdShadowing", true); // Default true

        $this->RegisterVariableBoolean('EvaluationIndoorTemperature', 'Auswertung Innentemperaturen', ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'ICON' => 'temperature-high', 'OPTIONS' => $ActiveOptions]);
        $this->EnableAction('EvaluationIndoorTemperature');
        //$this->SetValue("EvaluationIndoorTemperature", false); // Default false
    }
    
    public function Migrate($JSONData) {
        parent::Migrate($JSONData);

        $data = json_decode($JSONData);
        if (isset($data->configuration->EnableRoomShadowingByTemperature)) {
            $this->SetValue("EvaluationIndoorTemperature", $data->configuration->EnableRoomShadowingByTemperature);
        }
        return json_encode($data);
    }

    public function ApplyChanges() {
        parent::ApplyChanges();
        
        //Unregister all messages
        $messageList = array_keys($this->GetMessageList());
        foreach ($messageList as $message) {
            $this->UnregisterMessage($message, VM_UPDATE);
        }
        
        //Delete all references in order to read them
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }
        
        if ($this->ReadPropertyInteger("GlobalShadowingStatusVariable") > 0) {
            $this->RegisterMessage($this->ReadPropertyInteger("GlobalShadowingStatusVariable"), VM_UPDATE);
            $this->RegisterReference($this->ReadPropertyInteger("GlobalShadowingStatusVariable"));
        }
        if ($this->ReadPropertyInteger("InputTemperatureCurrentVariable") > 0) {
            $this->RegisterMessage($this->ReadPropertyInteger("InputTemperatureCurrentVariable"), VM_UPDATE);
            $this->RegisterReference($this->ReadPropertyInteger("InputTemperatureCurrentVariable"));
        }
        if ($this->ReadPropertyInteger("InputOutdoorTemperature") > 0) {
            $this->RegisterMessage($this->ReadPropertyInteger("InputOutdoorTemperature"), VM_UPDATE);
            $this->RegisterReference($this->ReadPropertyInteger("InputOutdoorTemperature"));
        }
        if ($this->ReadPropertyInteger("InputTemperatureTargetVariable") > 0) {
            $this->RegisterReference($this->ReadPropertyInteger("InputTemperatureTargetVariable"));
        }
    }
    
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data) {
        //https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/messages/
        if ($Message == VM_UPDATE) {          
            $this->validateShadowing($Data[0], $SenderID);
        }
    }
    
    public function SetActive(bool $Value) {
        if ($this->GetValue('Active') !== $Value) {
            $this->SetValue('Active', $Value);
        }
    }

    public function SetColdShadowing(bool $Value) {
        if ($this->GetValue('ColdShadowing') !== $Value) {
            $this->SetValue('ColdShadowing', $Value);
        }
    }

    public function SetEvaluationIndoorTemperature(bool $Value) {
        if ($this->GetValue('EvaluationIndoorTemperature') !== $Value) {
            $this->SetValue('EvaluationIndoorTemperature', $Value);
        }
    }

    public function RequestAction($Ident, $Value) {
        switch ($Ident) {
            case 'Active':
                $this->SetActive($Value);
                break;
            case 'ColdShadowing':
                $this->SetColdShadowing($Value);
                break;
            case 'EvaluationIndoorTemperature':
                $this->SetEvaluationIndoorTemperature($Value);
                break;
            default:
                throw new Exception('Invalid ident');
        }
    }

    private function validateShadowing($data, $senderId) {
        //Exit if global shadowing is disabled
        $globalShadowingStatus = GetValue($this->ReadPropertyInteger('GlobalShadowingStatusVariable'));
        $this->SendDebug('globalShadowingStatus', json_encode($globalShadowingStatus), 0);
        $this->SendDebug('EvaluationIndoorTemperature', json_encode($this->GetValue('EvaluationIndoorTemperature')), 0);

        if ($senderId == $this->ReadPropertyInteger('GlobalShadowingStatusVariable')) {
            if (($globalShadowingStatus === true) && ($this->GetValue('EvaluationIndoorTemperature') === false)) {
                // Activate only if global Status = true and RoomControl = false, otherwise enablement is controlled via Temperature Rule
                $this->SendDebug('status', "enabled by globalShadowingStatus and EvaluationIndoorTemperature = false", 0);
                $this->SetActive(true);
                return true;
            }
        }

        if ($globalShadowingStatus === false) {
            $this->SetActive(false);
            $this->SendDebug('status', "disabled by globalShadowingStatus", 0);
            return false;
        }

        // Check Outdoor Temperature
        if (($this->GetValue('ColdShadowing') === false) && ($this->ReadPropertyInteger('InputOutdoorTemperature') > 1)) {
            $outdoorTemp = floatval(GetValue($this->ReadPropertyInteger('InputOutdoorTemperature')));
            $threshold = $this->ReadPropertyFloat('ThresholdTemperature');
            if ($outdoorTemp < $threshold) {
                $this->SetActive(false);
                $this->SendDebug('status', "disabled by ColdShadowing", 0);
                return false;
            }
        }

        // Exit if we don't want temperature based shadowing
        if ($this->GetValue('EvaluationIndoorTemperature') === false) {
            $this->SendDebug('exit', "EvaluationIndoorTemperature is false", 0);
            return false;
        }

        if (($this->ReadPropertyInteger('InputTemperatureCurrentVariable') <= 1) || ($this->ReadPropertyInteger('InputTemperatureTargetVariable') <= 1)) {
            $this->SendDebug('exit', "input indoor Tempereratures are not set", 0);
            return false;
        }

        $curTemp = floatval(GetValue($this->ReadPropertyInteger('InputTemperatureCurrentVariable')));
        $tarTemp = floatval(GetValue($this->ReadPropertyInteger('InputTemperatureTargetVariable')));
        
        if ($curTemp >= $tarTemp) {
            $this->SetActive(true);
            $this->SendDebug('status', "disabled by Indoor Temperature", 0);
        } elseif ($curTemp < $tarTemp) {
            $this->SetActive(false);
            $this->SendDebug('status', "enabled by Indoor Temperature", 0);
        }
    }

    public function ImportFromCurrentRoom() {
        // find General Category & identify Bool Variable for global shadowing
        $foundGeneralCategory = false;
        $cat = IPS_GetCategoryList();
        foreach ($cat as $c) {
            $catInfo = IPS_GetObject($c);
            if ($catInfo['ObjectName'] == 'Beschattung') {
                $pc = IPS_GetObject(IPS_GetParent($catInfo['ObjectID']));
                if ($pc['ObjectName'] == "Allgemein") {
                    $foundGeneralCategory = true;
                    $ShadowingCatId = $catInfo['ObjectID'];
                    break;
                }
            }
        }
        $varBeschattungsID = @IPS_GetVariableIDByName('Aktivierung globale Beschattung', $ShadowingCatId);
        if ($varBeschattungsID === false) {
            // Creates Category & Global Variable
            if ($foundGeneralCategory === false) {
                $CatID = IPS_CreateCategory();
                IPS_SetName($CatID, "Allgemein");
                IPS_SetParent($CatID, 0);

                $CatID_BS = IPS_CreateCategory();
                IPS_SetName($CatID_BS, "Beschattung");
                IPS_SetParent($CatID_BS, $CatID);
            } else {
                $CatID_BS = $ShadowingCatId; // Category already exists, only Variable is missing
                $this->SendDebug('import', "Category already exists, only Variable was missing", 0);
            }
            //Create Bool Variable
            $varBeschattungsID = IPS_CreateVariable(0);
            IPS_SetName($varBeschattungsID, "Aktivierung globale Beschattung");
            IPS_SetVariableCustomPresentation($varBeschattungsID, ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'ICON' => 'power-off']);
            IPS_SetParent($varBeschattungsID, $CatID_BS);
            
            //Create Default Action Script
            $ScriptID = IPS_CreateScript(0);
            IPS_SetName($ScriptID, "Aktionsskript");
            IPS_SetScriptContent($ScriptID, "<?php SetValue(\$_IPS['VARIABLE'], \$_IPS['VALUE']); ?>");
            IPS_SetParent($ScriptID, $varBeschattungsID);
            IPS_SetVariableCustomAction($varBeschattungsID, $ScriptID);
        }
        $this->UpdateFormField('GlobalShadowingStatusVariable', 'value', $varBeschattungsID);
        
        // find FHK14 in current room
        $parent = IPS_GetParent($this->InstanceID);
        $childs = IPS_GetChildrenIDs ($parent);
        foreach ($childs as $child) {
            $ch = IPS_GetObject($child);
            if ($ch['ObjectType'] != 1) { continue; }

            $ch = IPS_GetInstance($child);
            if ($ch['ModuleInfo']['ModuleID'] == '{7C25F5A6-ED34-4FB4-8A6D-D49DFE636CDC}') {
                // FHK14 found
                $InstanceChilds = IPS_GetChildrenIDs($ch['InstanceID']); //Search for Variables with Current & Target Temperature
                $varCurrent = false;
                $varTarget = false; 
                foreach ($InstanceChilds as $ic) {
                    $name = IPS_GetName($ic);
                    if (preg_match("/Ist-Temperatur/", $name)) {
                        $varCurrent = $ic;
                        continue;
                    }
                    if (preg_match("/\Sollwert/", $name) && !preg_match("/\(Thermostat\)/", $name)) {
                        $varTarget = $ic;
                        continue;
                    }
                }
                if ($varCurrent === false || $varTarget === false) {
                    throw new Exception($this->Translate('Current/Target Temperatur variables not found in FHK14'));
                }
                
                // Update form fields with found variables
                $this->UpdateFormField('InputTemperatureCurrentVariable', 'value', $varCurrent);
                $this->UpdateFormField('InputTemperatureTargetVariable', 'value', $varTarget);
                
                break;
            }
        }

        // find Eltako Weatherstation
        $ch = IPS_GetInstanceListByModuleID('{9E4572C0-C306-4F00-B536-E75B4950F094}');
        if (count($ch) > 0) {
            $childs = IPS_GetChildrenIDs($ch[0]);
            foreach ($childs as $child) {
                $name = IPS_GetName($child);
                if (preg_match("/Temperatur AVG/i", $name)) {
                    $this->UpdateFormField('InputOutdoorTemperature', 'value', $child);
                    break;
                } elseif ($name == "Temperatur") {
                    // Fallback if AVG Variable is not present
                    $this->UpdateFormField('InputOutdoorTemperature', 'value', $child);
                }
            }
        }
    }

}
?>