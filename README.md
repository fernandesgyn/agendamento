# Agendamento AGEHAB

Sistema de agendamento de data e hora em **PHP 8.1+ e MySQL 8+**, pensado prioritariamente para uso em celular.

## Funcionalidades

- Link individual por identificador: `?id=12345` (recomendado) ou `?cpf=00000000000`.
- Datas e horários parametrizáveis por painel administrativo.
- Capacidade configurável por horário (seed inicial: 6 vagas).
- Horários lotados deixam de ser exibidos ao cidadão.
- Confirmação explícita antes de gravar o agendamento.
- Transação e bloqueio de linha (`SELECT ... FOR UPDATE`) para impedir overbooking em acessos simultâneos.
- Um agendamento ativo por identificador; ao escolher outro horário, o sistema faz o reagendamento.
- Painel administrativo com ocupação por data/horário e relação dos identificadores agendados.
- Exportação CSV dos agendamentos.
- Interface responsiva/mobile-first inspirada na identidade visual do Aluguel Social/AGEHAB.

## Instalação

1. Crie um banco MySQL e execute `database/schema.sql`.
2. Copie `.env.example` para `.env` e informe as credenciais do banco e do administrador.
3. Garanta PHP 8.1+ com a extensão `pdo_mysql` habilitada.
4. Em produção, configure o servidor web para usar a pasta `public/` como DocumentRoot.
5. Acesse `/admin/` para administrar datas, horários e vagas.

Para rodar rapidamente com o servidor embutido do PHP, a partir da raiz do projeto:

```bash
php -S localhost:8080 -t public
```

Depois acesse `http://localhost:8080/admin/` para o painel ou, por exemplo, `http://localhost:8080/?id=TESTE001` para simular um link individual.

Exemplo Apache (VirtualHost):

```apache
DocumentRoot /var/www/agendamento/public
<Directory /var/www/agendamento/public>
    AllowOverride All
    Require all granted
</Directory>
```

## Links enviados por WhatsApp

Preferencialmente use um ID interno que não revele dado pessoal:

```text
https://seu-dominio.gov.br/?id=ABC123456
```

Também é suportado CPF:

```text
https://seu-dominio.gov.br/?cpf=00000000000
```

> Para reduzir exposição de dados pessoais em histórico do navegador, logs e ferramentas de mensageria, o parâmetro `id` é preferível ao CPF.

## Seed de CPFs para teste

O sistema não exige cadastro prévio de CPF: qualquer CPF válido passado no parâmetro `cpf` pode abrir a tela de agendamento. O seed em `database/seeds/seed_cpfs.php` existe apenas para preencher alguns horários com agendamentos sintéticos e permitir testar lotação, vagas restantes e desaparecimento de horários lotados.

Antes de executar o seed, o banco precisa ter sido criado com `database/schema.sql` e o arquivo `.env` deve estar configurado corretamente.

No Windows, a partir da raiz do projeto:

```bat
copy .env.example .env
```

Edite o `.env` e confira pelo menos:

```text
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=agendamento
DB_USERNAME=root
DB_PASSWORD=
```

Depois execute simplesmente:

```bat
seed.bat
```

Ou diretamente pelo PHP:

```bat
php database\seeds\seed_cpfs.php
```

Se estiver usando XAMPP e o comando `php` não for reconhecido:

```bat
C:\xampp\php\php.exe database\seeds\seed_cpfs.php
```

Para verificar se o PHP está disponível:

```bat
php -v
```

Para verificar se a extensão MySQL do PHP está habilitada:

```bat
php -m | findstr pdo_mysql
```

Ao terminar, o script mostra no terminal os CPFs sintéticos gerados e os horários nos quais foram inseridos.

## Datas e horários iniciais

O `schema.sql` já cria, como configuração inicial solicitada:

- 14/08/2026
- 15/08/2026
- 16/08/2026

Horários: 07:00, 08:00, 09:00, 10:00, 11:00, 13:00, 14:00, 15:00, 16:00, 17:00, 18:00 e 19:00, com 6 vagas por horário.

Essas datas podem ser alteradas, desativadas ou substituídas no painel administrativo.

## Segurança e privacidade

- PDO com prepared statements.
- CSRF em gravações públicas e administrativas.
- Sessão de administrador com cookie `HttpOnly` e `SameSite=Lax`.
- Restrição no banco para impedir mais de um agendamento ativo por identificador.
- Recomenda-se HTTPS obrigatório em produção.
- Não versionar o arquivo `.env`.
- Trocar a senha administrativa antes de publicar.

## Estrutura

```text
app/                 bootstrap, banco, helpers e autenticação
public/              aplicação web
public/admin/        painel administrativo
database/schema.sql  estrutura e carga inicial
database/seeds/      massa de teste
seed.bat              atalho para executar o seed no Windows
.env.example         parâmetros de ambiente
```
