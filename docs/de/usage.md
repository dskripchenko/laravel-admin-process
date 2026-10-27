---
title: Verwendung
locale: de
status: stable
---

# Verwendung

## Die Instanzliste

Spalten: ID (öffnet die Instanzseite), Prozess, Version, Status, aktueller
Zustand, Business Key, ob es offene Incidents gibt, erstellt und
aktualisiert. Der aktuelle Zustand sind die aktiven Blattzustände oder der
Endzustand einer beendeten Instanz.

Filter: Prozess, Status, Business Key, "With incidents", erstellt
zwischen. Die Liste aktualisiert sich alle `refresh.list` Sekunden selbst.

Zeilenaktionen – **Suspend**, **Resume**, **Cancel** – erfordern
`admin.process.operate` und eine Begründung. Cancel kann zuvor die
abgeschlossenen Schritte kompensieren.

## Die Incident-Liste

Offene Incidents zuerst, dann die neuesten. Die Instanzspalte öffnet die
Instanzseite, wo die Ursache behoben wird – ein Incident hält nur fest,
dass etwas eine Person braucht. **Resolve incident** (mit
`admin.process.operate`) schließt ihn mit einer Lösung und einer
Begründung.

## Die Instanzseite

`/admin/screens/process-instance?id=<uuid>`.

Die Zusammenfassung oben: Status, aktueller Zustand und seine Bezeichnung,
Business Key, Subjekt, Elternprozess, Zeitstempel und der letzte Schritt,
der die Instanz bewegt hat.

Tabs:

| Tab | Inhalt |
|---|---|
| **Diagram** | der Prozessgraph mit der Position der Instanz: besuchte Zustände, die aktuellen, durchlaufene Übergänge, Probleme (ein Effekt mit unbekanntem Ausgang, ein offener Incident). Abgefragt alle `refresh.flow` Sekunden. Zusammengesetzte Zustände und Kindprozesse öffnen sich an Ort und Stelle. |
| **Waiting for** | die Events, die die Instanz jetzt annimmt (mit dem Zielzustand und ob ein Guard entscheidet), und ihre Timer. |
| **Context** | die Prozessdaten als JSON. |
| **Effects** | alle Effekte, neueste zuerst: Status, Semantik, Versuch, nächster Versuch, Fehler. |
| **Incidents** | Incidents und die Events, die warten oder abgelehnt wurden. |
| **Journal** | die neuesten Einträge, neueste zuerst: was passiert ist, wer, warum. |

## Prozesse und ihre Diagramme

**Processes** listet die neueste Version jedes Prozesses mit der Anzahl der
Instanzen, die laufen, an einem Incident warten und am letzten Tag
fehlgeschlagen sind. Eine Zeile öffnet die Prozessseite,
`/admin/screens/process-definition?name=<process>`:

- **Diagram** – der gesamte Prozess mit der Anzahl der Instanzen in jedem
  Zustand. Abschaltbare Stufen tragen einen Schalter an ihrem Knoten: Ihn
  auszuschalten (Berechtigung `admin.process.switches`) schaltet die Stufe
  überall ab, und Instanzen, die in ihr stehen, verlassen sie.
- **Stages** – alle abschaltbaren Stufen: an oder aus, warum, wer und wann;
  eine automatische Abschaltung zeigt die Fehlermeldung und das System als
  Akteur.
- **Instances** – die neuesten zwanzig, jede ein Link auf ihre Seite.
- **Start** (Berechtigung `admin.process.start`) – für Prozesse, die in
  `admin-process.startable` aufgeführt sind: der Startkontext als JSON und
  die Stufen, die für diesen Lauf abgeschaltet werden. Die Seite der neuen
  Instanz öffnet sich.

```php
// config/admin-process.php
'startable' => [
    OsmHazards::class,                         // starts with an empty context
    GarUpdate::class => ['regions' => []],     // the form opens with this context
],
```

## Batches auf der Instanzseite

Der Tab **Batches** listet die Batch-Stufen der Instanz – Status, Elemente
`finished / total` mit den Zählern, ein Fehler – sowie die fehlgeschlagenen
und zurückgestellten Elemente mit ihrer Begründung und ihren Versuchen. Das
Diagramm zeigt den Fortschritt eines laufenden Batches an seinem Knoten.
Zwei Operator-Aktionen (Berechtigung `admin.process.operate`, mit einer
Begründung): **Retry batch elements** (fehlgeschlagene, zurückgestellte oder
nicht gefundene Elemente eines laufenden Batches) und **Skip the rest of the
batch**.

## Operator-Aktionen

Die Befehlsleiste der Seite. Jede Aktion verlangt eine Begründung, die
zusammen mit dem Admin ins Journal geht.

| Aktion | Berechtigung | Engine-Aufruf |
|---|---|---|
| Suspend / Resume | operate | `suspend` / `resume` – Events warten, Effekte starten nicht |
| Cancel | operate | `cancel`, optional zuerst mit Kompensation |
| Retry effect | operate | `retryEffect` für einen fehlgeschlagenen, abgebrochenen oder unbekannten Effekt |
| … mit "I take responsibility" | **manage** | `retryEffect(confirmUnsafe: true)` – ein At-most-once-Effekt mit unbekanntem Ausgang |
| Resolve incident | operate | `resolveIncident` |
| Redeliver | operate | `redeliver` eines abgelehnten Events |
| Move to state | manage | `transitionTo`, mit oder ohne Exit- und Entry-Aktionen |
| Edit context | manage | `patchContext` – ein JSON-Objekt `{"key": value}` setzt Keys der obersten Ebene; ein JSON-Patch-Array (RFC 6902) erledigt alles andere |
| Settle an effect | manage | `resolveEffect` – erfolgreich mit einem Ergebnis oder fehlgeschlagen mit einem Code |
| Skip effect | manage | `skipEffect` – fortfahren, als wäre er erfolgreich gewesen |
| Retry batch elements | operate | `retryBatchItems` – fehlgeschlagene, zurückgestellte oder nicht gefundene Elemente eines laufenden Batches zurück in seine Warteschlange |
| Skip the rest of the batch | operate | `skipBatch` – was wartet, wird übersprungen, der Zustand geht weiter |

Im Diagramm hat die Seitenleiste eines ausgewählten Zustands **Move to this
state** (manage), mit Bestätigung.

Die Engine wendet eine manuelle Operation in Reihenfolge mit den anderen
Events der Instanz an. Mit einer synchronen Queue ist sie bereits
ausgeführt, wenn das Panel antwortet, sodass eine Ablehnung (der Effekt ist
nicht fehlgeschlagen, die Instanz ist beendet) als Fehler erscheint. Mit
einer echten Queue meldet das Panel die Operation als angenommen, und das
Ergebnis erscheint im Journal.

## Sicherheit

Die Methoden der Seite sind an Aktionen mit Berechtigungen gebunden, daher
verweigert das Panel einem Benutzer ohne die jeweilige Berechtigung eine
Operator-Methode, auch wenn sie direkt über die API aufgerufen wird. Die
Tests des Packs prüfen, dass jede Operator-Methode der Seite einem
Betrachter verweigert wird.

## Anpassung

Die Dashboard-Karte ist ein gewöhnliches Widget: Schränken Sie mit
`ProcessOverviewWidget::make()->permission(...)` ein, wer sie sieht, wenn
Sie sie auf einem eigenen Dashboard platzieren. Die
Aktualisierungsintervalle und die Journal-Länge stehen in der
Konfiguration.
