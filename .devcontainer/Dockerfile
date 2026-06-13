FROM mcr.microsoft.com/devcontainers/dotnet:6

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    php-cli php-xml php-mbstring unzip curl apt-transport-https gnupg2 ca-certificates lsb-release build-essential unixodbc-dev \
  && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
  && rm -rf /var/lib/apt/lists/*

# Install Microsoft SQL Server tools (sqlcmd)
RUN curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add - \
  && curl https://packages.microsoft.com/config/ubuntu/22.04/prod.list > /etc/apt/sources.list.d/mssql-release.list \
  && apt-get update \
  && ACCEPT_EULA=Y apt-get install -y msodbcsql18 mssql-tools \
  && echo 'export PATH="$PATH:/opt/mssql-tools/bin"' >> /etc/profile.d/mssql.sh \
  && rm -rf /var/lib/apt/lists/*

USER vscode
