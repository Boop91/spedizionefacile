# Script operativi PUDO

Questa cartella contiene helper manuali legati al fallback PUDO.

- `ATTIVA_PUDO.bat` -> attiva migration + seeding su Windows
- `IMPORTA_PUDO.bat` -> importa i dati PUDO mock direttamente nel database SQLite
- `setup-pudo-fallback.sh` -> attivazione rapida da shell Unix
- `check-pudo-sqlite.php` -> controllo leggero del database locale SQLite

Questi file non fanno parte del runtime dell'applicazione.  
Servono solo per operazioni manuali e diagnostica locale, quindi devono stare in `scripts/pudo/` e non nel root del backend.

Nota operativa:
- gli script risolvono da soli il percorso del backend `laravel-spedizionefacile-main`
- il manuale collegato e' in `docs/riferimento/operativo/PUDO_FALLBACK_SETUP.md`
