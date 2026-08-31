# Agendamento AGEHAB

Sistema de agendamento de data e hora em **PHP 8.1+ e MySQL 8+**, pensado prioritariamente para uso em celular.

## Funcionalidades

- Link individual por identificador: `?id=12345` ou `?cpf=00000000000`.
- Somente CPFs/IDs previamente cadastrados na lista de pessoas autorizadas podem agendar.
- A pessoa autorizada e ainda sem agendamento pode escolher data e horário normalmente.
- Depois da primeira confirmação, o agendamento é definitivo no link público: não pode ser alterado ou excluído pelo cidadão.
- Datas e horários parametrizáveis por painel administrativo.
- Capacidade configurável por horário (configuração inicial: 6 vagas).
- Horários lotados deixam de ser exibidos ao cidadão.
- Cada data mostra o total de vagas ainda disponíveis.
- Confirmação explícita antes de gravar o agendamento.
- Transação e bloqueio de linha (`SELECT ... FOR UPDATE`) para impedir overbooking em acessos simultâneos.
- Painel administrativo com pessoas autorizadas, situação LIVRE/AGENDADO, ocupação por data/horário e relação dos agendamentos.
- Exportação CSV dos agendamentos.
- Interface responsiva/mobile-first inspirada na identidade visual do Aluguel Social/AGEHAB.

## Instalação nova

1. Crie o banco MySQL e execute `database/schema.sql`.
2. Copie `.env.example` para `.env` e informe as credenciais do banco e do administrador.
3. Garanta PHP 8.1+ com a extensão `pdo_mysql` habilitada.
4. Em produção, configure o servidor web para usar a pasta `public/` como DocumentRoot.
5. Acesse `/admin/` para administrar pessoas, datas, horários e vagas.

Para rodar rapidamente com o servidor embutido do PHP, a partir da raiz do projeto:

```bash
php -S localhost:8080 -t public
```

## Atualização de banco já existente

Se o banco já havia sido criado antes da inclusão da lista de pessoas autorizadas, execute:

```text
database/migrations/20260831_add_authorized_subjects.sql
```

Para ambiente de teste, executar novamente `seed.bat` também cria automaticamente essa tabela caso ela ainda não exista.

## Pessoas autorizadas

A tabela `authorized_subjects` representa as pessoas que receberam autorização para escolher um horário.

Ela é independente da tabela `appointments`:

- `authorized_subjects`: pessoa cadastrada para poder agendar;
- `appointments`: horário efetivamente escolhido pela pessoa.

Assim uma pessoa pode estar cadastrada e continuar **LIVRE**, sem qualquer horário vinculado.

No painel `/admin/` é possível cadastrar CPF ou ID e visualizar a situação:

- **LIVRE**: pode abrir o link e realizar o primeiro agendamento;
- **AGENDADO**: já confirmou um horário e não pode escolher outro pelo link público.

## Links enviados por WhatsApp

Com CPF cadastrado:

```text
https://seu-dominio.gov.br/?cpf=00000000000
```

Ou com ID previamente cadastrado:

```text
https://seu-dominio.gov.br/?id=ABC123456
```

Por privacidade, quando houver um identificador interno disponível, ele é preferível ao CPF na URL.

## Seed de CPFs para teste

O seed `database/seeds/seed_cpfs.php` cria **60 CPFs sintéticos cadastrados na lista de pessoas autorizadas**.

A maioria fica **sem horário**, permitindo testar o fluxo real de escolha e confirmação.

Apenas uma parte recebe reservas previamente criadas para simular visualmente:

- horário lotado;
- horário com apenas 1 vaga restante;
- horário com poucas vagas disponíveis.

Depois do seed, o terminal mostra duas listas separadas:

```text
CPFs CADASTRADOS E LIVRES PARA FAZER NOVO AGENDAMENTO
...

CPFs JA AGENDADOS - SOMENTE PARA SIMULAR OCUPACAO
...
```

Para testar um novo agendamento, escolha obrigatoriamente um CPF da primeira lista.

No Windows:

```bat
seed.bat
```

Ou:

```bat
php database\seeds\seed_cpfs.php
```

Se estiver usando XAMPP e o comando `php` não for reconhecido:

```bat
C:\xampp\php\php.exe database\seeds\seed_cpfs.php
```

Depois copie um dos CPFs indicados como **LIVRE** e acesse, por exemplo:

```text
http://localhost:8080/?cpf=CPF_LIVRE_EXIBIDO_NO_TERMINAL
```

## Datas e horários iniciais

O `schema.sql` cria inicialmente:

- 14/08/2026
- 15/08/2026
- 16/08/2026

Horários: 07:00, 08:00, 09:00, 10:00, 11:00, 13:00, 14:00, 15:00, 16:00, 17:00, 18:00 e 19:00, com 6 vagas por horário.

As datas, horários e capacidades podem ser alterados no painel administrativo.

## Segurança e privacidade

- PDO com prepared statements.
- CSRF em gravações públicas e administrativas.
- Sessão de administrador com cookie `HttpOnly` e `SameSite=Lax`.
- Lista de pessoas previamente autorizadas.
- Restrição no banco para impedir mais de um agendamento ativo por identificador.
- Agendamento público definitivo após confirmação.
- Recomenda-se HTTPS obrigatório em produção.
- Não versionar o arquivo `.env`.
- Trocar a senha administrativa antes de publicar.

## Estrutura

```text
app/                 bootstrap, banco, helpers e autenticação
public/              aplicação web
public/admin/        painel administrativo
database/schema.sql  estrutura completa para instalação nova
database/migrations/ ajustes para bancos já existentes
database/seeds/      massa de teste
seed.bat             atalho para executar o seed no Windows
.env.example         parâmetros de ambiente
```
