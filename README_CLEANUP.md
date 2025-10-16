CLEANUP_NOTE.md (versione sintetica per IA future)
# gm_v3 — Nota di contesto (pulizia repository)

📅 Data: 16 ottobre 2025  
📂 Branch attivo: `main` (aggiornato con merge da `backup-pre-clean`)

## Contesto
Questo repository (`gm_v3`) è la **versione ripulita** del progetto originale.  
La pulizia è stata fatta per alleggerire il codice e mantenere solo i file realmente usati dalla web app (dashboard, AI, calendario, Google, ecc.).

I vecchi file di test e diagnostica non più necessari sono stati **spostati**, non cancellati, nella cartella:


_dev/archive-2025-10-16/


## Copia originale ("incasinata")
Il codice completo prima della pulizia si trova nel **branch**:


backup-pre-clean

È identico alla `main` prima del cleanup.  
Serve come backup nel caso si debba recuperare qualcosa o verificare comportamenti precedenti.

## Uso per future sessioni AI
Se in futuro una nuova sessione di ChatGPT / Codex / Aura non riconosce il contesto:
> “Questo è il progetto gm_v3. La versione attuale (main) è stata ripulita da file inutili.  
> La versione precedente si trova nel branch ‘backup-pre-clean’.  
> Se serve ripristinarla o confrontarla, si può fare un merge o un checkout da quel branch.”



Lo puoi salvare come CLEANUP_NOTE.md o README_CLEANUP.md nella root del repo, così:
