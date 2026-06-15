Dos problemas visibles:

1. Token scopes: none — el PAT actual no tiene permisos, por eso no ve releases
2. No releases found — consecuencia directa del punto 1

Fix: crear un nuevo PAT con scope repo

En GitHub (desde el navegador):

1. Ve a https://github.com/settings/tokens/new
2. Note: deploy-sbapu03 (o similar)
3. Expiration: 1 year (o No expiration)
4. Marca el scope: ✅ repo (el checkbox padre, que incluye todos los sub-scopes)
5. Click Generate token — copia el ghp_...

En el servidor, reautenticar con el nuevo token

gh auth login --with-token <<< "ghp_TU_NUEVO_TOKEN"
gh auth status

Ahora debería mostrar Token scopes: repo (o 'repo', 'read:org').

Verificar

gh release list --repo cubanote816/claesen-analytics

Deberías ver production-latest. Dime qué muestra.