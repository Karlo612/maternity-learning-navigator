FROM python:3.12-slim@sha256:229a2c5bfa27522db7815ea81f9bed70af17ccb9de9fc7ad142b1877b5830d36
ENV PYTHONDONTWRITEBYTECODE=1 PYTHONUNBUFFERED=1 PIP_NO_CACHE_DIR=1
WORKDIR /service
COPY ml-service/requirements.runtime.lock.txt ./
RUN pip install --requirement requirements.runtime.lock.txt
COPY ml-service/app ./app
COPY ml-service/tests ./tests
COPY resources/demo_samples.csv /governed/demo_samples.csv
COPY governance/demo_review_manifest.json /governed/demo_review_manifest.json
COPY app/resources/js/interface-catalog.json /governed/interface-catalog.json
RUN python -m app.train_demo \
        --dataset /governed/demo_samples.csv \
        --review-manifest /governed/demo_review_manifest.json \
        --interface-catalog /governed/interface-catalog.json \
        --output /demo-model \
    && useradd --create-home --uid 10001 navigator \
    && chown -R navigator:navigator /service /demo-model
USER navigator
EXPOSE 8000
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000", "--no-access-log"]
