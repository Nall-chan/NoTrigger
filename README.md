[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-2.5-blue.svg)]()
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
[![Version](https://img.shields.io/badge/Symcon%20Version-5.1%20%3E-green.svg)](https://www.symcon.de/forum/threads/30857-IP-Symcon-5-1-%28Stable%29-Changelog)
[![StyleCI](https://styleci.io/repos/45338150/shield?style=flat)](https://styleci.io/repos/45338150)  

# Symcon-Modul: Variablenüberwachung  
## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang) 
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Variablenüberwachung (single)](#4-variablen%C3%BCberwachung-single)
5. [Variablenüberwachung (group)](#5-variablen%C3%BCberwachung-group)
6. [Variablen im Ziel-Script](#6-variablen-im-ziel-script)
7. [Parameter / Modul-Infos](#7-parameter--modul-infos)
8. [Anhang](#8-anhang)
9. [Spenden](#9-spenden)
10. [Lizenz](#10-lizenz)

## 1. Funktionsumfang

Überwachen von IPS-Variablen auf Aktualisierung oder Veränderung.  
Grundidee war festzustellen ob bestimme (Status)Variablen regelmäßig aktualisiert werden, um dann entsprechend hierauf reagieren zu können.  
  
Beispiele:  
  
-- Homematic-Rauchmelder  
Alle paar (5?) Tage senden diese ihren Status an die CCU/Lan-Adapter. Sollte ein Melder dies nicht mehr machen, sollte eine eMail versendet werden.  
  
-- EM1000 / CUL  
Alle 5 Minuten werden die Datensätze von den Geräten gesendet, sollte dies nicht mehr passieren, soll die I/O Instanz einmal geschlossen und neu geöffnet werden.  
  
-- Daten von einem Gerät im Netzwerk per Push oder Poll.  
Alle 10 Sekunden kommen Daten per (beliebiges Netzwerkgerät) rein. Im Fehlerfall soll ein Script das Gerät per Telnet neu starten.  
  
-- eMail-Abfrage  
Alle 5 Minuten soll IPS eMails prüfen. Ändert sich der Zeitstempel der Variable 'Last message' 15min lang nicht, wird eine Meldung auf dem Webfront ausgegeben.  
  
-- 1Wire / ModBus  
Alle 10 Sek werden Werte gelesen. Ändern ein Sensor sich 60 Sekunden lang nicht ist entweder abgeklemmt oder defekt => Meldung per eMail  
  
-- Erinnerung Lüften  
Einmal am Tag müssen drei Fenster zum Lüften geöffnet werden, nach 36h wird eine Meldung erzeugt das noch nicht gelüftet wurde.  
  
etc....  
  
Die Funktion besteht im wesentlichen darin festzustellen ob sich eine Variable ändert bzw. aktualisiert.  
Sollte Dies innerhalb der konfigurierten Intervall-Zeit geschehen, wird keine Aktion ausgelöst.  
Nach Ablauf der Intervall-Zeit wird ein	eingestelltes Ziel-Skript gestartet bzw. eine Statusvariable gesetzt.  
Über das vom Benutzer selber zu erzeugende Ziel-Skript können dann weitere Maßnahmen und Steuerungen erfolgen (WFC_Notification / eMail / Steckdose aus & einschalten etc).  


## 2. Voraussetzungen

 - IPS ab Version 5.1  
 
## 3. Installation

**IPS 5.1:**  
   Bei privater Nutzung:
     Über den 'Module-Store' in IPS.  
   **Bei kommerzieller Nutzung (z.B. als Errichter oder Integrator) wenden Sie sich bitte an den Autor.**  

## 4. Variablenüberwachung (single)

 Unter Instanz hinzufügen ist die Variablenüberwachung unter (Sonstige) zu finden.  
 Jeweils einmal als Typ Single und Group.  
        
 Nach dem Anlegen der Instanz ist diese noch entsprechend zu konfigurieren.  
        
 - Aktiv :  
    Um die Überwachung zu aktivieren bzw. desaktivieren.  
        
 - Variable:  
    Nur bei Single lässt sich hier die zu überwachende Variable auswählen.  
        
 - Prüfmodus:  
    Legt fest ob sich der Wert der Variable(n) verändert haben muss, oder ob es reichte das eine Variable aktualisiert wurde auch wenn sich der Wert nicht geändert hat.  
        
 - Intervall:  
    Der Zeitraum in Sekunden in dem sich die Variable(n) nach dem unter Prüfmodus festgelegten Modus geändert haben muss. Wird  dieser Zeitraum überschritten, wird die Statusvariable 'STATE'  gesetzt und/oder das Ziel-Script gestartet. (max. Wert 599000000)  
        
 - Statusvariable 'STATE' verwenden:  
    Hiermit kann eine Statusvariable der Instanz zu/weg geschaltet werden. (z.B. zur Visualisierung).  
        
 - Skript:  
    Ziel-Script welches ausgeführt wird, wenn der  Überwachungszeitraum überschritten wurde. Das Script wird  ebenfalls ausgeführt, wenn die Überwachung wieder in  'Ruhe' geht nachdem die überwachte(n) Variable(n) sich nach dem unter Prüfmodus festgelegten Modus geändert haben.  
        
 - Neustart-Verzögerung:  
    Grundsätzlich wird immer ein Alarm ausgelöst, wenn die letzte Änderung/Aktualisierung der zu überwachenden Variable länger her ist als der eingestellte Intervall. Dies kann bei einem Dienst-Neustart zu falschen Meldungen führen.  
    Beispiele wo keine Verzögerung nötig ist sind z.B. Geräte welche sich nur einmal pro Woche / Monat etc. melden sollten, da es hier sehr unwahrscheinlich ist das ein Neustart genau in diesen Zeitpunkt fällt wo sich die Variable ändern sollte. Hier ist es sogar ungünstig mit einer Verzögerung zu arbeiten, weil dann vielleicht erst nach 10 statt 5 Tagen auffällt dass der Rauchmelder schon lange nicht mehr sendet.  

    Bei z.B. 1-Wire/Modbus Geräten und anderen Instanzen welche IPS mit einem internen Timer ausließt, sollte die Verzögerung auf Intervall stehen. Somit hat IPS beim starten erst mal Zeit die Geräte abzufragen bzw. die Daten zu lesen, bevor es zu einen Alarm kommt.  
    (IPS-Neustart 30 Sekunden, letzter Wert vor Neustart ist 0sek, Intervall ist 5 Sekunden. => Startet IPS, gibt es ohne eingestellter Verzögerung gleich einen Alarm, da der letzte Wert vor über 30 Sekunden gelesen wurde und somit größer als der eingestellte Intervall von 5 Sekunden ist.)  
    Die Verzögerung 'bis Aktualisierung' sollte für Geräte genutzt werden, wo man nicht genau weiß wann Sie nach einen Neustart wieder mit IPS kommunizieren. Die Überwachung und somit die Intervall-Zeit beginnt erst, wenn die Variable geändert/aktualisiert wurde.  
    Dies birgt aber auch ein Risiko: Sollte nach dem Neustart die Variable nie geändert/aktualisiert werden, wird auch nie ein Alarm erzeugt.  
        
 - Mehrfachauslösung:  
    Normalerweise wird nur beim Übergang von Ruhe/Alarm und Alarm/Ruhe die eigene Statusvariable gesetzt und das Ziel-Script gestartet. Wird die Mehrfachauslösung aktiviert, werden auch bei Updates von Ruhe/Ruhe und Alarm/Alarm alle Aktionen ausgelöst.  
    So wird jetzt bei jedem OnUpdate oder OnChange (je nach Modus) die eigene Statusvariable aktualisiert und das Ziel-Script gestartet.  


## 5. Variablenüberwachung (group)

 Die Konfiguration und die Funktion sind  nahezu identisch zu der Variante 'Single'.  
 Folgendes ist jedoch zu beachten:  
        
 Die zu überwachenden Variablen müssen als Link unterhalb Dieser Instanz liegen. Somit entfällt auch in der Konfiguration der Punkt Variable.  
 Alle zu überwachenden Variablen sind immer ODER verknüpft, es reicht also wenn Eine sich nicht innerhalb der Intervallzeit ändert/aktualisiert um eine Alarm-Meldung zu generieren. Im Umkehrschluss heißt dies dass die Ruhemeldung nur auslöst, wenn alle überwachten Variablen sich innerhalb der Intervallzeit ändert/aktualisiert haben.  
 Der Punkt Mehrfachauslösung unterscheidet sich entsprechend von der Funktion zu 'Single'.  
 Normalerweise wird hier nur beim Übergang von Ruhe/Alarm und Alarm/Ruhe, als Summe aller überwachten Variablen, die eigene Statusvariable gesetzt und das Ziel-Script gestartet.  
 Wird die Mehrfachauslösung aktiviert, wird jetzt einzeln für jede überwachte Variable der Übergang Ruhe/Alarm und Alarm/Ruhe alle Aktionen ausgelöst.  

## 6. Variablen im Ziel-Script

 Folgende Felder im Array der PHP-Variable $_IPS stehen im Ziel-Script zur Verfügung:  

| Index    | Typ     | Beschreibung                                             |
| :-------:|:------: | :------------------------------------------------------: |
| VALUE    | boolean | Aktueller Status wobei True = Alarm und False = Ruhe ist |
| OLDVALUE | boolean | vorheriger Wert                                          |
| EVENT    | integer | Instanz ID der auslösenden Variablenüberwachung          |
| VARIABLE | integer | ID der Variable welche die Auslösung verursacht hat      |
| SENDER   | string  | 'NoTrigger' FixString                                    |

 Das Ziel-Script sollte immer den Wert 'VALUE' abfragen, damit unterschieden werden kann ob es sich um eine Alarm-Meldung oder Ruhe-Meldung handelt:  

```php
        if ($_IPS['VALUE'])
        {
            // Alarm wurde ausgelöst
            // Jetzt Gerät aus- und einschalten
            // Und eMail versenden
        } else {
            // Ruhemeldung nach	Alarm
            // eMail das alles wieder gut ist
        }
```

## 7. Parameter / Modul-Infos

**GUIDs der Instanzen (z.B. wenn Instanz per PHP angelegt werden soll):**  

| Instanz                       | GUID                                   |
| :---------------------------: | :------------------------------------: |
| Variablenüberwachung (Single) | {BACCE313-C8F2-4189-B128-74A6888DAD21} |
| Variablenüberwachung (Group)  | {28198BA1-3563-4C85-81AE-8176B53589B8} |

**Eigenschaften von Variablenüberwachung (Single):**  

| Eigenschaft   | Typ     | Standardwert | Funktion                                                                  |
| :-----------: | :-----: | :----------: | :-----------------------------------------------------------------------: |
| Active        | boolean | false        | Aktivieren / Deaktivieren der Überwachung                                 |
| VarID         | integer | 0            | Variable welche überwacht werden soll                                     |
| CheckMode     | integer | 0            | Überwachung auf Aktualisierung (0) oder Änderung (1)                      |
| Intervall     | integer | 0            | Zeit in Sek bis zum Auslösen eines Alarm                                  |
| HasState      | boolean | true         | Variable 'STATE' anlegen                                                  |
| ScriptID      | integer | 0            | Ziel-Script                                                               |
| StartUp       | integer | 0            | Neustart-Verzögerung 0 = keine, 1 = Intervallzeit, 2 = bis Aktualisierung |
| MultipleAlert | boolean | false        | Mehrfachauslösung                                                         |

**Eigenschaften von Variablenüberwachung (Group):**  

| Eigenschaft   | Typ     | Standardwert | Funktion                                                                  |
| :-----------: | :-----: | :----------: | :-----------------------------------------------------------------------: |
| Active        | boolean | false        | Aktivieren / Deaktivieren der Überwachung                                 |
| CheckMode     | integer | 0            | Überwachung auf Aktualisierung (0) oder Änderung (1)                      |
| Intervall     | integer | 0            | Zeit in Sek bis zum Auslösen eines Alarm                                  |
| HasState      | boolean | true         | Variable 'STATE' anlegen                                                  |
| ScriptID      | integer | 0            | Ziel-Script                                                               |
| StartUp       | integer | 0            | Neustart-Verzögerung 0 = keine, 1 = Intervallzeit, 2 = bis Aktualisierung |
| MultipleAlert | boolean | false        | Mehrfachauslösung                                                         |


## 8. Anhang

 Idee von MCS-51 mit dem IPSLibary-Modul IPS-Health welche nie offiziell Verbreitet wurde.  
 Umsetzung von Nall-chan als natives IPS-Modul für IPS ab Version 3.1  

**Changlog:**  

Version 2.5:  
 - Release für IPS 5.1 und den Module-Store   

Version 2.2:  
 - Fixes für IPS 5.0  

Version 2.1:  
 - Fix: Timer in Create verschoben  

Version 2.0:  
 - Erste Version für IPS 4.1  

Version 1.1:  
 - Erstes öffentliches Release im Forum  

Version 1.0.0.7:  
 - Erstes internes Release mit Gruppenüberwachung  

## 9. Spenden  
  
  Die Library ist für die nicht kommzerielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:  

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=G2SLW2MEMQZH2" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a>


## 10. Lizenz  

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
