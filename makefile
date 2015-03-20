all:
	-rm nirror.zip
	-rm -rf nirror/
	mkdir nirror
	cp config.xml nirror/
	cp config_fr.xml nirror/
	cp config_es.xml nirror/
	cp config_tr.xml nirror/
	mkdir nirror/translations
	cp translations/fr.php nirror/translations/
	cp translations/es.php nirror/translations/
	cp translations/tr.php nirror/translations/
	cp nirror.php nirror
	cp logo.png nirror
	cp -r OAuth2/ nirror/
	zip -r nirror.zip nirror/
	rm -rf nirror
