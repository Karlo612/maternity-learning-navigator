FROM python:3.12-slim@sha256:229a2c5bfa27522db7815ea81f9bed70af17ccb9de9fc7ad142b1877b5830d36
ENV PYTHONDONTWRITEBYTECODE=1 PYTHONUNBUFFERED=1 PIP_NO_CACHE_DIR=1
WORKDIR /service
COPY requirements.runtime.lock.txt ./
RUN pip install --requirement requirements.runtime.lock.txt
COPY app ./app
COPY tests ./tests
RUN useradd --create-home --uid 10001 navigator && chown -R navigator:navigator /service
USER navigator
EXPOSE 8000
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000", "--no-access-log"]
