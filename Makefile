I18N = ~/Dev/depage-cms/www/framework/i18n.sh
JSMIN = ~/Dev/depage-cms/www/framework/JsMin/minimize

.PHONY: all min minjs locale locale-php sass sassc push pushdev pushlive

all: locale

locale:
	cd www/framework/ ; $(I18N)
	php www/framework/Cms/js/locale.php

tags:  $(wildcard www/framework/**/*.php)
	phpctags -R -C tags-cache

push: pushdev pushlive

pushlive: all
	rsync \
	    -k -r -v -c \
	    --exclude '.DS_Store' \
	    --exclude '.git' \
	    --exclude 'cache/' \
	    www/framework www/conf www/index.php jonas@depage.net:/var/www/depagecms/net.depage.edit/

pushdev: all
	rsync \
	    -k -r -v -c \
	    --exclude '.DS_Store' \
	    --exclude '.git' \
	    --exclude 'cache/' \
	    www/framework www/conf www/index.php jonas@twins:/var/www/depagecms/net.depage.edit/
