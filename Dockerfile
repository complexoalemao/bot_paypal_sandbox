# Usa imagem PHP CLI
FROM php:8.2-cli

# Define o diretório de trabalho
WORKDIR /app

# Copia todos os arquivos do repositório
COPY . .

# Expõe a porta que o Render vai usar
EXPOSE 10000

# Comando para iniciar o bot como servidor web
CMD ["php", "-S", "0.0.0.0:10000", "bot_paypal_sandbox.php"]
