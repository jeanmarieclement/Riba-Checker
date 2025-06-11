<?php
// validate.php - Validazione file Ri.Ba. DAT
// Tutti i commenti sono in inglese come da richiesta utente
header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_FILES['datfile']) || $_FILES['datfile']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'No file uploaded or upload error.']);
        exit;
    }
    $file = $_FILES['datfile']['tmp_name'];
    $content = file_get_contents($file);
    // Remove any Windows line endings
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
    // Remove all empty lines
    $lines = array_values(array_filter($lines, function($l){ return trim($l) !== ''; }));
    $errors = [];
    $parsed = [];


    // Full validation logic for Ri.Ba. DAT (CBI-RIB-001 6_02)

    // 1. Check all lines and collect warnings for invalid length
    $line_warnings = [];
    foreach ($lines as $idx => $line) {
        if (mb_strlen($line, 'UTF-8') !== 120) {
            $line_warnings[$idx+1] = "Line ".($idx+1).": Invalid length (".mb_strlen($line, 'UTF-8')." chars, expected 120).";
        }
    }

    // 2. Analizza comunque i record principali, anche se la lunghezza non è corretta
    $detailed_errors = [];
    $parsed = [];
    $n = count($lines);
    if ($n < 3) {
        $detailed_errors[] = "Il file contiene meno di 3 record (testa, almeno una ricevuta, coda).";
    } else {
        // IB
        $first = $lines[0];
        $ib = [];
        $ib['tipo_record'] = mb_substr($first,1,2,'UTF-8');
        $ib['codice_mittente'] = mb_substr($first,3,5,'UTF-8');
        $ib['codice_abi_ricevente'] = mb_substr($first,8,5,'UTF-8');
        $ib['data_creazione'] = mb_substr($first,13,6,'UTF-8');
        $ib['data_creazione_fmt'] = preg_match('/^[0-9]{6}$/',$ib['data_creazione']) ?
            substr($ib['data_creazione'],0,2)."/".substr($ib['data_creazione'],2,2)."/20".substr($ib['data_creazione'],4,2) : $ib['data_creazione'];
        $ib['nome_supporto'] = mb_substr($first,19,20,'UTF-8');
        $ib['codice_divisa'] = mb_substr($first,113,1,'UTF-8');
        // Check IB fields
        if ($ib['tipo_record'] !== 'IB') $detailed_errors[] = "Record IB: tipo_record non 'IB' (pos 2-3, trovato '{$ib['tipo_record']}').";
        if (!preg_match('/^[0-9]{5}$/',$ib['codice_mittente'])) $detailed_errors[] = "Record IB: codice mittente non valido (pos 4-8, trovato '{$ib['codice_mittente']}').";
        if (!preg_match('/^[0-9]{5}$/',$ib['codice_abi_ricevente'])) $detailed_errors[] = "Record IB: codice ABI ricevente non valido (pos 9-13, trovato '{$ib['codice_abi_ricevente']}').";
        if (!preg_match('/^[0-9]{6}$/',$ib['data_creazione'])) $detailed_errors[] = "Record IB: data creazione non valida (pos 14-19, trovato '{$ib['data_creazione']}').";
        if (trim($ib['nome_supporto'])=='') $detailed_errors[] = "Record IB: nome supporto obbligatorio (pos 20-39, trovato '{$ib['nome_supporto']}').";
        if ($ib['codice_divisa'] !== 'E') $detailed_errors[] = "Record IB: codice divisa non 'E' (pos 114, trovato '{$ib['codice_divisa']}').";
        $parsed['IB'] = $ib;
        // EF
        $last = $lines[$n-1];
        $ef = [];
        $ef['tipo_record'] = mb_substr($last,1,2,'UTF-8');
        $ef['codice_mittente'] = mb_substr($last,3,5,'UTF-8');
        $ef['codice_abi_ricevente'] = mb_substr($last,8,5,'UTF-8');
        $ef['data_creazione'] = mb_substr($last,13,6,'UTF-8');
        $ef['data_creazione_fmt'] = preg_match('/^[0-9]{6}$/',$ef['data_creazione']) ?
            substr($ef['data_creazione'],0,2)."/".substr($ef['data_creazione'],2,2)."/20".substr($ef['data_creazione'],4,2) : $ef['data_creazione'];
        $ef['nome_supporto'] = mb_substr($last,19,20,'UTF-8');
        $ef['numero_disposizioni'] = mb_substr($last,45,7,'UTF-8');
        $ef['totale_importi_negativi'] = mb_substr($last,52,15,'UTF-8');
        $ef['totale_importi_positivi'] = mb_substr($last,67,15,'UTF-8');
        $ef['numero_record'] = mb_substr($last,82,7,'UTF-8');
        $ef['codice_divisa'] = mb_substr($last,113,1,'UTF-8');
        if ($ef['tipo_record'] !== 'EF') $detailed_errors[] = "Record EF: tipo_record non 'EF' (pos 2-3, trovato '{$ef['tipo_record']}').";
        foreach(['codice_mittente','codice_abi_ricevente','data_creazione'] as $f) {
            if ($ef[$f] !== $ib[$f]) $detailed_errors[] = "Record EF: campo '$f' non coerente con IB (trovato '{$ef[$f]}', atteso '{$ib[$f]}').";
        }
        if (trim($ef['nome_supporto'])!==trim($ib['nome_supporto'])) $detailed_errors[] = "Record EF: nome supporto non coerente con IB (trovato '{$ef['nome_supporto']}', atteso '{$ib['nome_supporto']}').";
        if (!preg_match('/^[0-9]{7}$/',$ef['numero_disposizioni'])) $detailed_errors[] = "Record EF: numero disposizioni non valido (pos 46-52, trovato '{$ef['numero_disposizioni']}').";
        if (!preg_match('/^[0-9]{15}$/',$ef['totale_importi_negativi'])) $detailed_errors[] = "Record EF: totale importi negativi non valido (pos 53-67, trovato '{$ef['totale_importi_negativi']}').";
        if (!preg_match('/^[0]{15}$/',$ef['totale_importi_positivi'])) $detailed_errors[] = "Record EF: totale importi positivi deve essere zero (pos 68-82, trovato '{$ef['totale_importi_positivi']}').";
        if (!preg_match('/^[0-9]{7}$/',$ef['numero_record'])) $detailed_errors[] = "Record EF: numero record non valido (pos 83-89, trovato '{$ef['numero_record']}').";
        if ($ef['codice_divisa'] !== 'E') $detailed_errors[] = "Record EF: codice divisa non 'E' (pos 114, trovato '{$ef['codice_divisa']}').";
        $parsed['EF'] = $ef;
        // Ricevute
        $groups = [];
        for($i=1;$i<$n-1;$i+=7) {
            
            $block = array_slice($lines,$i,7);
            if (count($block)<7) {
                $detailed_errors[] = "Ricevuta incompleta a partire dalla riga ".($i+1).".";
                continue;
            }
            $expected = ['14','20','30','40','50','51','70'];
            for($j=0;$j<7;$j++) {
                $tipo = mb_substr($block[$j],1,2,'UTF-8');
                if ($tipo!==$expected[$j]) {
                    $detailed_errors[] = "Ricevuta a riga ".($i+1).": atteso record tipo '{$expected[$j]}' ma trovato '$tipo'.";
                }
            }
            // Check progressivo (pos 4-10)
            $progressivi = [];
            for($j=0;$j<7;$j++) $progressivi[] = mb_substr($block[$j],3,7,'UTF-8');
            if (count(array_unique($progressivi))!==1) {
                $detailed_errors[] = "Ricevuta a riga ".($i+1).": progressivi non coerenti (".implode(",",$progressivi).")";
            }
            // Record 14 dettagliato
            $r14 = $block[0];
            $r14_fields = [
                'data_pagamento' => mb_substr($r14,22,6,'UTF-8'),
                'causale' => mb_substr($r14,28,5,'UTF-8'),
                'importo' => mb_substr($r14,33,13,'UTF-8'),
                'segno' => mb_substr($r14,46,1,'UTF-8'),
                'abi_assuntrice' => mb_substr($r14,47,5,'UTF-8'),
                'cab_assuntrice' => mb_substr($r14,52,5,'UTF-8'),
                'abi_domiciliataria' => mb_substr($r14,57,5,'UTF-8'),
                'cab_domiciliataria' => mb_substr($r14,62,5,'UTF-8'),
                'codice_azienda_creditrice' => mb_substr($r14,91,5,'UTF-8'),
                'tipo_codice' => mb_substr($r14,96,1,'UTF-8'),
                'codice_divisa' => mb_substr($r14,119,1,'UTF-8')
            ];
            if (!preg_match('/^[0-9]{6}$/',$r14_fields['data_pagamento'])) $detailed_errors[] = "Record 14 a riga ".($i+1).": data pagamento non valida (pos 23-28, trovato '{$r14_fields['data_pagamento']}').";
            if ($r14_fields['causale']!=='30000') $detailed_errors[] = "Record 14 a riga ".($i+1).": causale non '30000' (pos 29-33, trovato '{$r14_fields['causale']}').";
            if (!preg_match('/^[0-9]{13}$/',$r14_fields['importo'])) $detailed_errors[] = "Record 14 a riga ".($i+1).": importo non valido (pos 34-46, trovato '{$r14_fields['importo']}').";
            if ($r14_fields['segno']!=='-') $detailed_errors[] = "Record 14 a riga ".($i+1).": segno non '-' (pos 47, trovato '{$r14_fields['segno']}').";
            if (!preg_match('/^[0-9]{5}$/',$r14_fields['abi_assuntrice'])) $detailed_errors[] = "Record 14 a riga ".($i+1).": ABI assuntrice non valido (pos 48-52, trovato '{$r14_fields['abi_assuntrice']}').";
            if (!preg_match('/^[0-9]{5}$/',$r14_fields['cab_assuntrice'])) $detailed_errors[] = "Record 14 a riga ".($i+1).": CAB assuntrice non valido (pos 53-57, trovato '{$r14_fields['cab_assuntrice']}').";
            if (!preg_match('/^[0-9]{5}$/',$r14_fields['abi_domiciliataria'])) $detailed_errors[] = "Record 14 a riga ".($i+1).": ABI domiciliataria non valido (pos 58-62, trovato '{$r14_fields['abi_domiciliataria']}').";
            if (!preg_match('/^[0-9]{5}$/',$r14_fields['cab_domiciliataria'])) $detailed_errors[] = "Record 14 a riga ".($i+1).": CAB domiciliataria non valido (pos 63-67, trovato '{$r14_fields['cab_domiciliataria']}').";
            // codice azienda creditrice è facoltativo: non segnalare errore se vuoto
            if (trim($r14_fields['codice_azienda_creditrice'])!=='' && !preg_match('/^[0-9]{5}$/',$r14_fields['codice_azienda_creditrice'])) {
                $detailed_errors[] = "Record 14 a riga ".($i+1).": codice azienda creditrice non valido (pos 92-96, trovato '{$r14_fields['codice_azienda_creditrice']}').";
            }
            if ($r14_fields['tipo_codice']!=='4') $detailed_errors[] = "Record 14 a riga ".($i+1).": tipo codice non '4' (pos 97, trovato '{$r14_fields['tipo_codice']}').";
            if ($r14_fields['codice_divisa']!=='E') $detailed_errors[] = "Record 14 a riga ".($i+1).": codice divisa non 'E' (pos 120, trovato '{$r14_fields['codice_divisa']}').";
            $data_pagamento_fmt = preg_match('/^[0-9]{6}$/',$r14_fields['data_pagamento']) ?
                substr($r14_fields['data_pagamento'],0,2)."/".substr($r14_fields['data_pagamento'],2,2)."/20".substr($r14_fields['data_pagamento'],4,2) : $r14_fields['data_pagamento'];
            $groups[] = [
                'progressivo' => $progressivi[0],
                'importo' => $r14_fields['importo'],
                'data_pagamento' => $r14_fields['data_pagamento'],
                'data_pagamento_fmt' => $data_pagamento_fmt
            ];
        }
        // Check totali
        $num_ric = count($groups);
        if (isset($ef['numero_disposizioni']) && preg_match('/^[0-9]+$/',$ef['numero_disposizioni'])) {
            if ((int)$ef['numero_disposizioni'] !== $num_ric) $detailed_errors[] = "EF: numero disposizioni non coincide col numero di ricevute ($num_ric, trovato {$ef['numero_disposizioni']}).";
        }
        $totale_importi = 0;
        foreach($groups as $g) $totale_importi += (int)$g['importo'];
        if (isset($ef['totale_importi_negativi']) && preg_match('/^[0-9]+$/',$ef['totale_importi_negativi'])) {
            if ((int)$ef['totale_importi_negativi'] !== $totale_importi) $detailed_errors[] = "EF: totale importi negativi non coincide con la somma degli importi (trovato {$ef['totale_importi_negativi']}, atteso $totale_importi).";
        }
        if (isset($ef['numero_record']) && preg_match('/^[0-9]+$/',$ef['numero_record'])) {
            if ((int)$ef['numero_record'] !== $n) $detailed_errors[] = "EF: numero record non coincide col numero di righe ($n, trovato {$ef['numero_record']}).";
        }
    }

    // Output dettagliato
    $human = "";
    if (count($line_warnings)) {
        $human .= "WARNING sulla lunghezza delle righe:\n".implode("\n", $line_warnings)."\n\n";
    }
    if (count($detailed_errors)) {
        $human .= "ERRORI di struttura/campi:\n".implode("\n", $detailed_errors)."\n\n";
    }
    // Tabella IB
    // Mapping abbreviazioni e tooltip per IB
    $ib_labels = [
        'tipo_record' => ['Tipo', 'Tipo record (IB), pos 2-3'],
        'codice_mittente' => ['Mitt.', 'Codice mittente (pos 4-8)'],
        'codice_abi_ricevente' => ['ABI ric.', 'Codice ABI ricevente (pos 9-13)'],
        'data_creazione' => ['Data', 'Data creazione distinta (pos 14-19)'],
        'nome_supporto' => ['Supporto', 'Nome supporto (pos 20-39)'],
        'codice_divisa' => ['Divisa', 'Codice divisa (pos 114)'],
        'data_creazione_fmt' => ['Data (formattata)', 'Data creazione formattata']
    ];
    if (isset($parsed['IB'])) {
        $ib = $parsed['IB'];
        $human .= "<h3>Record IB (Testata)</h3><div class='table-responsive'><table border='1' cellpadding='4' style='border-collapse:collapse'><tr>";
        foreach($ib_labels as $k=>$arr) if(isset($ib[$k])) $human .= "<th title='".htmlspecialchars($arr[1])."'>".htmlspecialchars($arr[0])."</th>";
        $human .= "</tr><tr>";
        foreach($ib_labels as $k=>$arr) if(isset($ib[$k])) $human .= "<td>".htmlspecialchars($ib[$k])."</td>";
        $human .= "</tr></table></div><br>";
    }
    // Mapping abbreviazioni e tooltip per EF
    $ef_labels = [
        'tipo_record' => ['Tipo', 'Tipo record (EF), pos 2-3'],
        'codice_mittente' => ['Mitt.', 'Codice mittente (pos 4-8)'],
        'codice_abi_ricevente' => ['ABI ric.', 'Codice ABI ricevente (pos 9-13)'],
        'data_creazione' => ['Data', 'Data creazione distinta (pos 14-19)'],
        'nome_supporto' => ['Supporto', 'Nome supporto (pos 20-39)'],
        'numero_disposizioni' => ['N.disp.', 'Numero disposizioni (pos 46-52)'],
        'totale_importi_negativi' => ['Tot. neg.', 'Totale importi negativi (pos 53-67)'],
        'totale_importi_positivi' => ['Tot. pos.', 'Totale importi positivi (pos 68-82)'],
        'numero_record' => ['N.rec.', 'Numero record (pos 83-87)'],
        'codice_divisa' => ['Divisa', 'Codice divisa (pos 114)'],
        'data_creazione_fmt' => ['Data (formattata)', 'Data creazione formattata']
    ];
    if (isset($parsed['EF'])) {
        $ef = $parsed['EF'];
        $human .= "<h3>Record EF (Coda)</h3><div class='table-responsive'><table border='1' cellpadding='4' style='border-collapse:collapse'><tr>";
        foreach($ef_labels as $k=>$arr) if(isset($ef[$k])) $human .= "<th title='".htmlspecialchars($arr[1])."'>".htmlspecialchars($arr[0])."</th>";
        $human .= "</tr><tr>";
        foreach($ef_labels as $k=>$arr) if(isset($ef[$k])) $human .= "<td>".htmlspecialchars($ef[$k])."</td>";
        $human .= "</tr></table></div><br>";
    }
    // Tabella Ricevute
    if (isset($groups) && count($groups)) {
        $human .= "<h3>Ricevute trovate</h3><div class='table-responsive'><table border='1' cellpadding='4' style='border-collapse:collapse'><tr><th>#</th>";
        // header dinamico
        $firstGroup = $groups[0];
        foreach($firstGroup as $k=>$v) if($k!=='data_pagamento_fmt') $human .= "<th>".htmlspecialchars($k)."</th>";
        $human .= "<th>data_pagamento (formattata)</th></tr>";
        foreach($groups as $i=>$g) {
            $human .= "<tr><td>".($i+1)."</td>";
            foreach($g as $k=>$v) if($k!=='data_pagamento_fmt') $human .= "<td>".htmlspecialchars($v)."</td>";
            $human .= "<td>".htmlspecialchars($g['data_pagamento_fmt'])."</td></tr>";
        }
        $human .= "</table></div><br>";
    }
    $status = (count($line_warnings)||count($detailed_errors)) ? 'error' : 'ok';
    echo json_encode(['status' => $status, 'human' => str_replace("\n", "<br>", $human)]);
    exit;

    $last = $lines[count($lines)-1];
    if (substr($first,1,2) !== 'IB') {
        $errors[] = "First record is not 'IB' (found '".substr($first,1,2)."').";
    }
    if (substr($last,1,2) !== 'EF') {
        $errors[] = "Last record is not 'EF' (found '".substr($last,1,2)."').";
    }

    // 3. Parse IB fields and check
    $ib = [
        'tipo_record' => substr($first,1,2),
        'codice_mittente' => substr($first,3,5),
        'codice_abi_ricevente' => substr($first,8,5),
        'data_creazione' => substr($first,13,6),
        'nome_supporto' => substr($first,19,20),
        'codice_divisa' => substr($first,113,1)
    ];
    if ($ib['tipo_record'] !== 'IB') $errors[] = "Record IB: tipo_record non 'IB'.";
    if (!preg_match('/^[0-9]{5}$/',$ib['codice_mittente'])) $errors[] = "Record IB: codice mittente non valido.";
    if (!preg_match('/^[0-9]{5}$/',$ib['codice_abi_ricevente'])) $errors[] = "Record IB: codice ABI ricevente non valido.";
    if (!preg_match('/^[0-9]{6}$/',$ib['data_creazione'])) $errors[] = "Record IB: data creazione non valida.";
    if (trim($ib['nome_supporto'])=='') $errors[] = "Record IB: nome supporto obbligatorio.";
    if ($ib['codice_divisa'] !== 'E') $errors[] = "Record IB: codice divisa non 'E'.";

    // 4. Parse EF fields and check
    $ef = [
        'tipo_record' => substr($last,1,2),
        'codice_mittente' => substr($last,3,5),
        'codice_abi_ricevente' => substr($last,8,5),
        'data_creazione' => substr($last,13,6),
        'nome_supporto' => substr($last,19,20),
        'numero_disposizioni' => substr($last,45,7),
        'totale_importi_negativi' => substr($last,52,15),
        'totale_importi_positivi' => substr($last,67,15),
        'numero_record' => substr($last,82,7),
        'codice_divisa' => substr($last,113,1)
    ];
    if ($ef['tipo_record'] !== 'EF') $errors[] = "Record EF: tipo_record non 'EF'.";
    foreach(['codice_mittente','codice_abi_ricevente','data_creazione'] as $f) {
        if ($ef[$f] !== $ib[$f]) $errors[] = "Record EF: campo '$f' non coerente con IB.";
    }
    if (trim($ef['nome_supporto'])!==trim($ib['nome_supporto'])) $errors[] = "Record EF: nome supporto non coerente con IB.";
    if (!preg_match('/^[0-9]{7}$/',$ef['numero_disposizioni'])) $errors[] = "Record EF: numero disposizioni non valido.";
    if (!preg_match('/^[0-9]{15}$/',$ef['totale_importi_negativi'])) $errors[] = "Record EF: totale importi negativi non valido.";
    if (!preg_match('/^[0]{15}$/',$ef['totale_importi_positivi'])) $errors[] = "Record EF: totale importi positivi deve essere zero.";
    if (!preg_match('/^[0-9]{7}$/',$ef['numero_record'])) $errors[] = "Record EF: numero record non valido.";
    if ($ef['codice_divisa'] !== 'E') $errors[] = "Record EF: codice divisa non 'E'.";

    // 5. Check received records structure
    $n = count($lines);
    $groups = [];
    for($i=1;$i<$n-1;$i+=7) {
        $block = array_slice($lines,$i,7);
        if (count($block)<7) {
            $errors[] = "Ricevuta incompleta a partire dalla riga ".($i+1).".";
            continue;
        }
        $expected = ['14','20','30','40','50','51','70'];
        for($j=0;$j<7;$j++) {
            $tipo = substr($block[$j],1,2);
            if ($tipo!==$expected[$j]) {
                $errors[] = "Ricevuta a riga ".($i+1).": atteso record tipo '{$expected[$j]}' ma trovato '$tipo'.";
            }
        }
        // Check progressivo (pos 4-10)
        $progressivi = [];
        for($j=0;$j<7;$j++) $progressivi[] = substr($block[$j],3,7);
        if (count(array_unique($progressivi))!==1) {
            $errors[] = "Ricevuta a riga ".($i+1).": progressivi non coerenti (".implode(",",$progressivi).")";
        }
        // Check campi obbligatori record 14 (esempio)
        $r14 = $block[0];
        if (!preg_match('/^[0-9]{6}$/',substr($r14,22,6))) $errors[] = "Record 14 a riga ".($i+1).": data pagamento non valida.";
        if (substr($r14,28,5)!=='30000') $errors[] = "Record 14 a riga ".($i+1).": causale non '30000'.";
        if (!preg_match('/^[0-9]{13}$/',substr($r14,33,13))) $errors[] = "Record 14 a riga ".($i+1).": importo non valido.";
        if (substr($r14,46,1)!=='-') $errors[] = "Record 14 a riga ".($i+1).": segno non '-'.";
        if (!preg_match('/^[0-9]{5}$/',substr($r14,47,5))) $errors[] = "Record 14 a riga ".($i+1).": ABI assuntrice non valido.";
        if (!preg_match('/^[0-9]{5}$/',substr($r14,52,5))) $errors[] = "Record 14 a riga ".($i+1).": CAB assuntrice non valido.";
        if (!preg_match('/^[0-9]{5}$/',substr($r14,57,5))) $errors[] = "Record 14 a riga ".($i+1).": ABI domiciliataria non valido.";
        if (!preg_match('/^[0-9]{5}$/',substr($r14,62,5))) $errors[] = "Record 14 a riga ".($i+1).": CAB domiciliataria non valido.";
        if (!preg_match('/^[0-9]{5}$/',substr($r14,91,5))) $errors[] = "Record 14 a riga ".($i+1).": codice azienda creditrice non valido.";
        if (substr($r14,96,1)!=='4') $errors[] = "Record 14 a riga ".($i+1).": tipo codice non '4'.";
        if (substr($r14,119,1)!=='E') $errors[] = "Record 14 a riga ".($i+1).": codice divisa non 'E'.";
        // Record 30: codice fiscale debitore (pos. 71-86)
        $r30 = $block[2];
        $cf = substr($r30,70,16);
        if (!preg_match('/^[A-Z0-9]{11,16}$/',$cf)) $errors[] = "Record 30 a riga ".($i+3).": codice fiscale debitore non valido.";
        // Filler a blank (spazi) per posizioni note (esempio)
        foreach([substr($r14,99,20),substr($r30,86,34)] as $filler) {
            if (trim($filler)!=='') $errors[] = "Filler non blank in ricevuta a riga ".($i+1).".";
        }
        $groups[] = [
            'progressivo' => $progressivi[0],
            'importo' => substr($r14,33,13),
            'cf_debitore' => $cf
        ];
    }

    // 6. Check totale effetti e numero record
    $num_ric = count($groups);
    if ((int)$ef['numero_disposizioni'] !== $num_ric) $errors[] = "EF: numero disposizioni non coincide col numero di ricevute ($num_ric).";
    $totale_importi = 0;
    foreach($groups as $g) $totale_importi += (int)$g['importo'];
    if ((int)$ef['totale_importi_negativi'] !== $totale_importi) $errors[] = "EF: totale importi negativi non coincide con la somma degli importi.";
    if ((int)$ef['numero_record'] !== $n) $errors[] = "EF: numero record non coincide col numero di righe ($n).";

    if (count($errors)) {
        echo json_encode(['status' => 'error', 'message' => implode('<br>', $errors)]);
        exit;
    }
    // Output human readable summary
    $human = "Record IB: " . json_encode($ib, JSON_UNESCAPED_UNICODE) . "\n";
    foreach($groups as $i=>$g) {
        $human .= "Ricevuta #".($i+1).": Progressivo {$g['progressivo']}, Importo ".number_format($g['importo']/100,2,',','.').", CF debitore {$g['cf_debitore']}\n";
    }
    $human .= "Record EF: " . json_encode($ef, JSON_UNESCAPED_UNICODE);
    echo json_encode(['status' => 'ok', 'human' => str_replace("\n", "<br>", $human)]);
    exit;
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Eccezione PHP: ' . $e->getMessage()]);
    exit;
}
// END
    