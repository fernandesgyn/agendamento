# Agendamento AGEHAB

Sistema de agendamento de data e hora em **PHP 8.1+ e MySQL 8+**, pensado prioritariamente para uso em celular.

## Fluxo público

O cidadão não recebe mais CPF ou identificador na URL.

O endereço público é simplesmente:

```text
https://seu-dominio.gov.br/
```

Na tela inicial a pessoa informa:

- CPF;
- data de nascimento.

O sistema procura um cadastro ativo com a combinação informada. Se não localizar, exibe:

```text
Cadastro não encontrado. Confira o CPF e a data de nascimento informados.
```

Quando os dados são encontrados, o sistema grava somente o `person_id` na sessão do servidor e libera as datas e horários disponíveis. O CPF e a data de nascimento não ficam na URL.

Depois da primeira confirmação, o agendamento é definitivo no acesso público e não pode ser alterado ou excluído pelo cidadão.

## Funcionalidades

- Login público por CPF + data de nascimento.
- Cadastro de pessoa com nome, CPF e data de nascimento.
- CPF único no banco de dados.
- Um único agendamento ativo por pessoa.
- Datas e horários parametrizáveis pelo painel administrativo.
- Capacidade configurável por horário (inicialmente 6 vagas).
- Horários lotados deixam de ser exibidos.
- Cada data mostra o total de vagas ainda disponíveis.
- Confirmação explícita antes de gravar o agendamento.
- `SELECT ... FOR UPDATE` para serializar tentativas simultâneas no mesmo horário e evitar overbooking.
- Painel administrativo com pessoas, situação LIVRE/AGENDADO, ocupação por data/horário e relação dos agendamentos.
- Exportação CSV com nome, CPF, nascimento, data e hora agendada.
- Interface mobile-first inspirada na identidade visual do Aluguel Social/AGEHAB.

## Banco de dados: sem migrations

Este projeto **não usa migrations**.

`database/schema.sql` é a fonte única e completa da estrutura do banco. Ele contém todas as tabelas, índices, relacionamentos, datas e horários iniciais necessários.

Tabelas:

- `people`: nome, CPF, data de nascimento e situação cadastral;
- `scheduling_days`: datas disponíveis;
- `scheduling_slots`: horários, capacidade e disponibilidade por data;
- `appointments`: agendamentos realizados, ligados à pessoa por `person_id`.

Os seeds inserem apenas massa de teste. Eles não criam nem alteram tabelas.

## Instalação

1. Execute `database/schema.sql` no MySQL.
2. Copie `.env.example` para `.env` e informe as credenciais do banco e do administrador.
3. Garanta PHP 8.1+ com `pdo_mysql` habilitado.
4. Em produção, use a pasta `public/` como DocumentRoot.
5. Acesse `/admin/` para administrar pessoas, datas, horários e vagas.

Servidor local:

```bash
php -S localhost:8080 -t public
```

Acesso público:

```text
http://localhost:8080/
```

Painel administrativo:

```text
http://localhost:8080/admin/
```

### Banco criado com versão anterior

Como o projeto não usa migrations, em desenvolvimento/homologação recrie o banco e execute novamente o schema completo.

Exemplo:

```bat
mysql -u root -p -e "DROP DATABASE IF EXISTS agendamento;"
mysql -u root -p < database\schema.sql
```

Depois, se desejar massa de teste:

```bat
seed.bat
```

## Pessoas

No painel administrativo cada pessoa possui:

- nome completo;
- CPF;
- data de nascimento;
- cadastro ativo/inativo;
- situação do agendamento: LIVRE ou AGENDADO.

**LIVRE** significa que a pessoa pode fazer login e escolher seu primeiro horário.

**AGENDADO** significa que ela já confirmou e não poderá selecionar outro horário pelo acesso público.

## Seed para teste

`database/seeds/seed_cpfs.php` cria 60 pessoas sintéticas com:

- CPF válido para teste;
- nome;
- data de nascimento.

A maioria fica sem agendamento. Uma parte recebe reservas para simular horários lotados ou com poucas vagas.

Ao executar:

```bat
seed.bat
```

o terminal mostra, por exemplo:

```text
PESSOAS LIVRES PARA TESTAR LOGIN E FAZER NOVO AGENDAMENTO

CPF ........... | Nascimento dd/mm/aaaa | Pessoa Teste XX
```

Use o CPF e a data de nascimento exibidos e acesse:

```text
http://localhost:8080/
```

O seed também mostra separadamente as pessoas já agendadas usadas apenas para simular ocupação.

## Datas e horários iniciais

O `schema.sql` cria inicialmente:

- 14/08/2026
- 15/08/2026
- 16/08/2026

Horários:

- 07:00
- 08:00
- 09:00
- 10:00
- 11:00
- 13:00
- 14:00
- 15:00
- 16:00
- 17:00
- 18:00
- 19:00

Cada horário começa com capacidade de 6 vagas.

## Concorrência e segurança

- PDO com prepared statements.
- CSRF em login, agendamento e operações administrativas.
- Sessões com cookie `HttpOnly` e `SameSite=Lax`.
- Regeneração do ID da sessão após login.
- CPF não é transportado na URL.
- Pessoa precisa combinar CPF + data de nascimento para acessar.
- CPF é único em `people`.
- Restrição no banco impede mais de um agendamento ativo por pessoa.
- A linha da pessoa é bloqueada durante a confirmação para impedir dois agendamentos simultâneos pela mesma conta.
- A linha do horário é bloqueada com `FOR UPDATE` antes da contagem das vagas, impedindo que acessos simultâneos ultrapassem a capacidade.
- Recomenda-se HTTPS obrigatório em produção.
- Não versionar `.env`.
- Trocar a senha administrativa antes da publicação.

## Estrutura

```text
app/                 bootstrap, banco, helpers e autenticação
public/              aplicação pública
public/admin/        painel administrativo
database/schema.sql  estrutura completa e única do banco
database/seeds/      massa de teste
seed.bat             atalho do seed no Windows
.env.example         parâmetros de ambiente
```
