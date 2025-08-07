# Raum Beschattungs-Steuerung
Das Modul erlaubt es auf Basis der Innentemperatur eines Raumes eine Steuervariable zu schalten, um darüber die Beschattung des Raumes zu ermöglichen.
Damit wird der Raum erst dann beschattet, wenn die Soll-Temperatur erreicht ist.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [Konfiguration](#6-konfiguration)
7. [Visualisierung](#7-visualisierung)
8. [PHP-Befehlsreferenz](#8-php-befehlsreferenz)


### 1. Funktionsumfang

* Überwacht die Innentemperatur mit Soll & Ist sowie den globalen Beschattungsstatus.

### 2. Voraussetzungen

- IP-Symcon ab Version 8.0

### 3. Software-Installation

* Über den Module Store das 'Raum Beschattungs-Steuerung'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen: https://github.com/migodev/RoomShadowingController

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'Raum Beschattungs-Steuerung'-Modul mithilfe des Schnellfilters gefunden werden.  
    - Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

### 5. Statusvariablen und Profile

Es werden keine Profile angelegt.
Es wird eine Statusvariable angelegt

Name                  | Typ					| Funktion
--------------------- | ------------------- | -------------------
Raum Beschattung aktiv | Boolean		| Zeigt den Status an, ob die Beschattung aktiv/inaktiv für den Raum ist

### 6. Konfiguration

| Eigenschaft                                           |   Typ   | Standardwert | Funktion                                                  |
|:------------------------------------------------------|:-------:|:-------------|:----------------------------------------------------------|
| Variable für globalen Beschattungsstatus              | integer | 0            | Die globale Variable die die globale Beschattung steuert. In der Regel unter Allgemein/Beschattung/Aktivierung globale Beschattung |
| Variable für aktuelle Raumtemperatur                  | integer | 0            | Die Variable welche die aktuelle Ist-Temperatur speichert. |
| Variable für Ziel Raumtemperatur                      | integer | 0            | Die Variable welche die aktuelle Soll-Temperatur speichert. |
| Raum-Beschattung nach Innen-Temperatur aktivieren     | boolean | true         | Aktiviert die Innenraum Temperatur-Steuerung, ist der Schalter deaktiviert, synchronisiert sich der Status der Instanz nur mit der globalen Variable. |
| <em>Action-Center</em>                                |  		  |              |  														 |
| Automatisch Zuordnen                                  |         |              | Via Klick wird die globale Beschattungs-Steuerung Variable gesucht sowie im aktuellen Raum das Eltako FHK14 und die Variablen automatisch vorbefüllt. |

### 7. Visualisierung

Das Modul bietet keine Funktion in der Visualisierung.

### 8. PHP-Befehlsreferenz

Keine