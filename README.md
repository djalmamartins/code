# Moves Code

Plataforma PHP modular para o CMS, o site institucional Moves e os aplicativos de clientes.

## Ambiente local

- URL: `https://code.lab`
- Redirecionamento: `http://code.lab` para HTTPS permanente (`301`)
- Document root: `/Applications/XAMPP/xamppfiles/htdocs/code/public`
- PHP: XAMPP 8.2.4
- Health check: `https://code.lab/health`

O certificado foi emitido por uma CA local do `mkcert`. A confiança é restrita ao computador no qual `mkcert -install` foi executado; o certificado não possui validade pública ou global.

## Composer

O autoload PSR-4 está configurado assim:

- `Moves\` → `source/` para o código próprio da aplicação.
- `container/` é carregado pelo classmap do Composer e contém os temas e seus controladores.
- As classes `MovesCode\*` são fornecidas pelas bibliotecas MovesCode instaladas em `vendor/`; não são carregadas de uma pasta `src/` local.

Para garantir o PHP compatível com o XAMPP:

```bash
/Applications/XAMPP/xamppfiles/bin/php /opt/homebrew/bin/composer install
/Applications/XAMPP/xamppfiles/bin/php /opt/homebrew/bin/composer dump-autoload
```

## Autenticação local

O acesso administrativo usa `MovesCode\Model\Connection`, configurada pelas
variáveis de ambiente `MOVES_DB_DSN`, `MOVES_DB_USER` e `MOVES_DB_PASSWORD`.
Nenhuma credencial padrão é definida pelo projeto. Configure essas variáveis no
ambiente do Apache/XAMPP antes de habilitar o login e aplique
`database/schema.sql` no banco escolhido. O schema cria somente as tabelas
`users` e `auth_attempts` caso ainda não existam; não cria banco, usuário ou
conta administrativa.

As rotas disponíveis são `GET/POST /login`, `POST /logout` e `GET /admin`.
O login requer contas provisionadas diretamente por um processo administrativo
confiável; não há cadastro público. Sem conexão ou schema, as páginas públicas
continuam disponíveis e operações de autenticação respondem genericamente com
HTTP 503.

## Apache e certificados

O VirtualHost foi adicionado a `/Applications/XAMPP/xamppfiles/etc/extra/httpd-vhosts.conf`. O certificado e a chave ficam fora de `public`:

- `/Applications/XAMPP/xamppfiles/etc/ssl.crt/code.lab.crt`
- `/Applications/XAMPP/xamppfiles/etc/ssl.key/code.lab.key`

A chave privada usa permissão `0640` e não deve ser versionada.

Backup inicial da configuração:

- `/Applications/XAMPP/xamppfiles/etc/backups/code-lab-20261008-064933/`

## Validação

```bash
/Applications/XAMPP/xamppfiles/bin/php -l public/index.php
/Applications/XAMPP/xamppfiles/bin/php /opt/homebrew/bin/composer validate --strict
/Applications/XAMPP/xamppfiles/bin/httpd -t
/Applications/XAMPP/xamppfiles/bin/httpd -S
curl -I http://code.lab
curl https://code.lab/health
```

### Resultado em 8 de outubro de 2026

- Apache do XAMPP: em execução nas portas 80 e 443.
- `httpd -t`: `Syntax OK`.
- Módulos: `ssl_module` e `rewrite_module` carregados.
- VirtualHosts: `code.lab`, `mvs.lab` e `moves.lab` carregados no mapa do Apache.
- PHP: `public/index.php` sem erros de sintaxe no PHP 8.2.4 do XAMPP.
- Composer: `composer.json` válido em modo estrito; requisito de plataforma PHP atendido.
- HTTP: `http://code.lab/` retorna `301` para `https://code.lab/`.
- HTTPS: página inicial retorna `200`, certificado verificado e aberto no navegador sem alerta.
- Health: `https://code.lab/health` retorna `200` e JSON com `status: ok`.
- Hosts: exatamente uma entrada `127.0.0.1 code.lab`.
- Logs de erro de `code.lab`: vazios após os testes.
- VirtualHosts existentes: `moves.lab` responde `302`; `mvs.lab` responde `500` enquanto o MySQL está parado. Nenhum arquivo desses projetos foi alterado e não há erro novo nos respectivos logs do Apache.

O MySQL e o ProFTPD permaneceram parados e não foram alterados, pois não são necessários para o diagnóstico inicial de `code.lab`.

Backup de `/etc/hosts` criado antes da alteração:

- `/etc/hosts.code-lab-backup-20261008-070339`

## Estrutura

Os diretórios existentes foram preservados. Nenhum arquivo dos projetos Realizze, MVS, Moves ou de outros diretórios de `htdocs` foi modificado.
