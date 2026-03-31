Regole obbligatorie per lavorare in squadra.

Prima di modificare: leggere _SQUADRA_DIARIO.md.
Dopo ogni intervento: scrivere in _SQUADRA_DIARIO.md cosa è stato fatto, quali file sono stati toccati, e come verificare.

Lavorare solo a turni: CAPO → ARCHITETTURA → INTERFACCIA → LOGICA → REVISIONE → CAPO.
Il lavoro in parallelo è consentito solo se coordinato.

Regole per il parallelo coordinato:
- Deve esistere sempre un coordinatore unico (`CAPO` o equivalente) che assegna i compiti, raccoglie i risultati e decide l'ordine finale.
- Gli agenti possono lavorare in parallelo solo su sotto-problemi indipendenti o con dipendenze dichiarate chiaramente.
- Ogni agente deve avere ownership esplicita del proprio ambito/file o della propria domanda di analisi.
- Prima di partire, il coordinatore deve definire: obiettivo, ruoli, input attesi, output attesi e criteri di handoff.
- Se un agente dipende da informazioni di un altro, deve fermarsi sull'assunzione rischiosa e aspettare l'handoff del ruolo piu' adatto.
- Nessun agente deve sovrascrivere o contraddire il lavoro di un altro senza passare dal coordinatore.
- Al termine di ogni tranche, il coordinatore sintetizza tutto e aggiorna `_SQUADRA_DIARIO.md`.
