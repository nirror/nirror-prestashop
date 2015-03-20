all:
	-rm nirror.zip
	-rm -rf nirror/
	mkdir nirror
	cp config.xml nirror/
	mkdir nirror/translations
	cp translations/fr.php nirror/translations/
	cp translations/es.php nirror/translations/
	cp translations/tr.php nirror/translations/
	cp nirror.php nirror
	cp logo.png nirror
	cp -r OAuth2/ nirror/
	cp Readme.md nirror/
	zip -r nirror.zip nirror/
	rm -rf nirror
