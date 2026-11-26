# IAcreditar — serviço intermediário KANDRIX

Este repositório contém o backend HTTP da IAcreditar para Render. O plugin Minecraft chama `https://SEU-SERVICO.onrender.com/v1/chat` por HTTPS. A chave da OpenAI fica somente nas variáveis privadas do Render.

## Deploy no Render

1. Crie um **Web Service** ligado a este repositório.
2. Em **Language/Runtime**, selecione **Docker**. O Dockerfile fica na raiz (`Dockerfile`).
3. Use o plano Free para testes.
4. Em **Environment**, crie estas variáveis:
   - `OPENAI_API_KEY`: chave da API OpenAI do projeto.
   - `OPENAI_MODEL`: identificador de um modelo habilitado na sua conta de API.
   - `IACREDITAR_SHARED_SECRET`: segredo longo e aleatório; use exatamente o mesmo no `bridge-secret` do plugin.
5. Faça o deploy. A rota será `https://NOME-DO-SERVICO.onrender.com/v1/chat`.

## Teste e segurança

- O endpoint aceita somente POST e exige o cabeçalho `X-IAcreditar-Secret`.
- Não coloque a chave OpenAI no GitHub, no plugin ou no `config.yml` do Minecraft.
- Não compartilhe o valor de `IACREDITAR_SHARED_SECRET` em capturas de tela.
- O plano gratuito pode suspender o serviço após inatividade; a primeira chamada posterior pode demorar para acordá-lo.
- Configure limites de uso e orçamento no painel da API.

O `OPENAI_MODEL` é obrigatório: use um identificador de modelo realmente disponível no seu projeto da API; o backend não inventa um modelo padrão.
