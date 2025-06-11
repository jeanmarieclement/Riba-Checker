# Riba Checker

Questo progetto fornisce un validatore per file Ri.Ba. in formato .dat secondo le specifiche CBI (CBI-RIB-001 6_02), pensato esclusivamente per la directory "Riba Checker".

## Funzionalità
- Upload e validazione di file .dat Ri.Ba. tramite interfaccia web
- Controllo delle regole principali secondo lo standard CBI
- Disclaimer e link al documento ufficiale

## Requisiti
- Server web con supporto PHP 7.2+
- Estensioni PHP: libxml, DOM

## Installazione
1. Carica tutti i file nella directory desiderata sul server web
2. Assicurati che i permessi siano corretti (644 per file, 755 per cartelle)
3. Verifica che le estensioni PHP richieste siano abilitate

Per dettagli sull'installazione e configurazione vedere anche il file `cbi-validator-setup.txt` in questa directory.

## Utilizzo
1. Accedi alla pagina `index.html` tramite browser
2. Trascina o seleziona un file `.dat` da validare
3. Visualizza i risultati della validazione direttamente sulla pagina

## Disclaimer
Questo strumento è fornito a scopo dimostrativo. La validazione è basata sulle specifiche CBI per Ri.Ba. DAT, ma non garantisce la completa conformità con tutti i requisiti bancari. Verificare sempre i file con strumenti ufficiali prima dell'invio.

## Collegamenti utili
- [Documento ufficiale CBI-RIBA.pdf](docs/CBI-RIBA.pdf)

## Licenza
Questo progetto è distribuito con licenza MIT. Vedi il file LICENSE per i dettagli.
