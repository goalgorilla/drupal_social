include .env

default: up

.PHONY: help
ifneq (,$(wildcard docker.mk))
help : docker.mk
	@sed -n 's/^##//p' $<
else
help : Makefile
	@sed -n 's/^##//p' $<
endif

## up : Start up containers, including the project_name defined at .env file.
.PHONY: up
up:
	@echo "Starting up containers for $(PROJECT_NAME)..."
	@docker compose -f docker-compose.nginx.yml -p nginx up -d
	@docker compose up -d --remove-orphans
	@echo "http://$(PROJECT_BASE_URL)"

## install : Install default Open Social profile.
.PHONY: install
install:
	@echo "Install Open Social profile on $(PROJECT_NAME)..."
	@docker exec -i $(PROJECT_NAME)_web bash /var/www/scripts/social/install/install_script.sh

## stop : Stop containers.
.PHONY: stop
stop:
	@echo "Stopping containers for $(PROJECT_NAME)..."
	@docker-compose stop

## prune : Remove container for this project.
.PHONY: prune
prune:
	@echo "Removing containers and volumes for $(PROJECT_NAME)..."
	@docker-compose down -v $(filter-out $@,$(MAKECMDGOALS))

## ps : List running project containers.
.PHONY: ps
ps:
	@docker ps --filter name='$(PROJECT_NAME)*'

## shell : Access the webserver container via shell.
.PHONY: shell
shell:
	docker exec -it '$(PROJECT_NAME)_web' /bin/bash
