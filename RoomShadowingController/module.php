<?php

declare(strict_types=1);

class RoomShadowingController extends IPSModule {
    
    public function Create() {
        parent::Create();
        
        //Properties
        $this->RegisterPropertyInteger('InputTemperatureCurrentVariable', 0);
        $this->RegisterPropertyInteger('InputTemperatureTargetVariable', 0);
        $this->RegisterPropertyInteger('GlobalShadowingStatusVariable', 0);
        $this->RegisterPropertyBoolean('EnableRoomShadowingByTemperature', true);
       
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

    public function RequestAction($Ident, $Value) {
        switch ($Ident) {
            case 'Active':
                $this->SetActive($Value);
                break;
            default:
                throw new Exception('Invalid ident');
        }
    }

    private function validateShadowing($data, $senderId) {
        //Exit if global shadowing is disabled
        $globalShadowingStatus = GetValue($this->ReadPropertyInteger('GlobalShadowingStatusVariable'));
       
        if ($senderId == $this->ReadPropertyInteger('GlobalShadowingStatusVariable')) {
            if (($globalShadowingStatus === true) && ($this->ReadPropertyBoolean('EnableRoomShadowingByTemperature') === false)) {
                // Activate only if global Status = true and RoomControl = false, otherwise enablement is controlled via Temperature Rule
                $this->SetActive(true);
                return true;
            }
        }

        if ($globalShadowingStatus === false) {
            $this->SetActive(false);
            return false;
        }

        // Exit if we don't want temperature based shadowing
        if ($this->ReadPropertyBoolean('EnableRoomShadowingByTemperature') === false) {
            return false;
        }

        if (($this->ReadPropertyInteger('InputTemperatureCurrentVariable') <= 1) || ($this->ReadPropertyInteger('InputTemperatureTargetVariable') <= 1)) {
            return false;
        }

        $curTemp = floatval(GetValue($this->ReadPropertyInteger('InputTemperatureCurrentVariable')));
        $tarTemp = floatval(GetValue($this->ReadPropertyInteger('InputTemperatureTargetVariable')));

        if ($curTemp >= $tarTemp) {
            $this->SetActive(true);
        } elseif ($curTemp < $tarTemp) {
            $this->SetActive(false);
        }
    }

    public function ImportFromCurrentRoom() {
        // find General Category & identify Bool Variable for global shadowing
        $cat = IPS_GetCategoryList ();
        foreach ($cat as $c) {
            $catInfo = IPS_GetObject($c);
            if ($catInfo['ObjectName'] == 'Beschattung') {
                $pc = IPS_GetObject(IPS_GetParent($catInfo['ObjectID']));
                if ($pc['ObjectName'] == "Allgemein") {
                    $ShadowingCatId = $catInfo['ObjectID'];
                    break;
                }
            }
        }
        $varBeschattungsID = @IPS_GetVariableIDByName('Aktivierung globale Beschattung', $ShadowingCatId);
        if ($varBeschattungsID === false) {
            throw new Exception('Globale Beschattung variable not found in symcon');
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
                    if (preg_match("/\(Thermostat\)/", $name)) {
                        $varTarget = $ic;
                        continue;
                    }
                }
                if ($varCurrent === false || $varTarget === false) {
                    throw new Exception('Ist/Soll Temperatur variables not found in FHK14');
                }
                
                // Update form fields with found variables
                $this->UpdateFormField('InputTemperatureCurrentVariable', 'value', $varCurrent);
                $this->UpdateFormField('InputTemperatureTargetVariable', 'value', $varTarget);
                
                return;
            }
        }
    }

}
?>