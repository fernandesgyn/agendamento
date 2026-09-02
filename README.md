# Agendamento AGEHAB

Sistema de agendamento em **PHP 8.1+ e MySQL/MariaDB**, mobile-first, com login público por CPF + data de nascimento e proteção transacional contra overbooking.

## URL de produção

```text
https://eloconnections.com.br/agendamento/
```

Painel administrativo:

```text
https://eloconnections.com.br/agendamento/admin/
```

O projeto está preparado para funcionar em uma subpasta. As URLs de assets, redirects, logout e painel usam `APP_BASE_PATH=/agendamento`.

## Publicação na Hostinger

### 1. Banco de dados

No hPanel, crie o banco MySQL e o usuário. Depois abra o banco no phpMyAdmin e importe:

```text
database/schema.sql
```

O `schema.sql` **não contém `CREATE DATABASE` nem `USE`**. Ele deve ser importado dentro do banco já criado no hPanel.

O projeto não utiliza migrations.

### 2. Arquivos

Envie o conteúdo completo deste repositório para:

```text
public_html/agendamento/
```

A estrutura ficará, por exemplo:

```text
public_html/
└── agendamento/
    ├── .htaccess
    ├── .env
    ├── app/
    ├── database/
    ├── public/
    └── ...
```

Não é necessário mover manualmente o conteúdo de `public/`. O `.htaccess` da raiz encaminha as URLs para a pasta `public` sem mostrar `/public` no endereço.

O mesmo `.htaccess` bloqueia acesso HTTP direto a `app/`, `database/`, `.env`, arquivos Git, README e seed.

### 3. Configuração `.env`

Copie `.env.example` para `.env` e preencha os dados reais fornecidos pela Hostinger:

```env
APP_ENV=production
APP_URL=https://eloconnections.com.br/agendamento
APP_BASE_PATH=/agendamento
APP_NAME=Agendamento AGEHAB
APP_TIMEZONE=America/Sao_Paulo

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=SEU_BANCO_HOSTINGER
DB_USERNAME=SEU_USUARIO_HOSTINGER
DB_PASSWORD=SUA_SENHA_HOSTINGER

ADMIN_USER=admin
ADMIN_PASSWORD=UMA_SENHA_FORTE
```

Use exatamente o host, nome do banco, usuário e senha exibidos no hPanel caso sejam diferentes dos exemplos.

### 4. PHP

Use PHP 8.1 ou superior e confirme que `pdo_mysql` está habilitado.

### 5. Testes após publicar

Abra:

```text
https://eloconnections.com.br/agendamento/
```

Verifique:

- carregamento da logo e estilos;
- login por CPF + data de nascimento;
- escolha de data e horário;
- confirmação do agendamento;
- logout;
- card de pendências documentais.

Depois teste:

```text
https://eloconnections.com.br/agendamento/admin/
```

Verifique login administrativo, cadastro de pessoas, datas, horários, ocupação e exportação CSV.

## Fluxo público

A pessoa informa:

- CPF;
- data de nascimento no formato `dd/mm/aaaa`.

Se a combinação não estiver cadastrada e ativa, o sistema informa que o cadastro não foi encontrado.

Quando autenticada, somente o `person_id` é mantido na sessão. CPF e data de nascimento não são enviados na URL.

Depois da primeira confirmação, o agendamento é definitivo no acesso público.

## Banco de dados

Tabelas:

- `people`: nome, CPF, data de nascimento e situação cadastral;
- `scheduling_days`: datas disponíveis;
- `scheduling_slots`: horários, capacidade e situação;
- `appointments`: agendamentos ligados à pessoa por `person_id`.

O banco possui restrição para apenas um agendamento ativo por pessoa.

## Concorrência

A confirmação utiliza transação MySQL/InnoDB e `SELECT ... FOR UPDATE`.

Quando duas pessoas disputam simultaneamente a última vaga do mesmo horário, a linha do horário é bloqueada. A segunda transação aguarda, reconta as vagas após a primeira concluir e é recusada se a capacidade já tiver sido atingida.

## Massa de teste

`database/seeds/seed_cpfs.php` insere pessoas sintéticas e alguns agendamentos para desenvolvimento/homologação. O seed não cria nem altera tabelas.

Não execute o seed em produção.

## Ambiente local

Para executar localmente com a pasta `public` como DocumentRoot, configure temporariamente:

```env
APP_ENV=local
APP_URL=http://localhost:8080
APP_BASE_PATH=
```

Depois rode:

```bash
php -S localhost:8080 -t public
```

Acesso público:

```text
http://localhost:8080/
```

Painel:

```text
http://localhost:8080/admin/
```

## Segurança

- PDO com prepared statements;
- CSRF em login e gravações;
- sessão com cookie `HttpOnly`, `SameSite=Lax` e `Secure` em HTTPS;
- cookie restrito ao caminho `/agendamento` em produção;
- regeneração do ID da sessão após login;
- CPF fora da URL;
- restrição de um agendamento ativo por pessoa;
- bloqueio transacional para impedir overbooking;
- `.env` não versionado e bloqueado via `.htaccess`;
- diretórios internos bloqueados para acesso HTTP;
- `X-Content-Type-Options: nosniff`;
- `Referrer-Policy: strict-origin-when-cross-origin`.

## Estrutura

```text
.htaccess            roteamento e proteção para Hostinger
app/                 bootstrap, banco, helpers e autenticação
public/              aplicação web
public/admin/        painel administrativo
database/schema.sql  estrutura completa do banco, sem migrations
database/seeds/      massa de teste
.env.example         configuração-base de produção
```
