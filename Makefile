#!/usr/bin/make -f
# 

package=awl
majorversion = $(shell sed -n 's:\([0-9\.]*\)[-a-f0-9-]*:\1:p' VERSION)
gitrev = 0
version = $(majorversion)
issnapshot = 0
snapshot : gitrev = $(shell git rev-parse --short HEAD)
snapshot : version = $(majorversion)-git$(gitrev)
snapshot : issnapshot = 1

all: built-docs 

built-docs: docs/api/phpdoc.ini inc/*.php
	phpdoc -c docs/api/phpdoc.ini || echo "WARNING: the docs could not be built"
	touch built-docs

#
# Insert the current version number into AWLUtilities.php
#
inc/AWLUtilities.php: scripts/build-AWLUtilities.sh VERSION inc/AWLUtilities.php.in
	scripts/build-AWLUtilities.sh <inc/AWLUtilities.php.in >inc/AWLUtilities.php


#
# Build a release .tar.gz file in the directory above us
#
release: built-docs
	-ln -s . $(package)-$(version)
	sed 's:@@VERSION@@:$(majorversion):' php-awl.spec.in | \
        sed 's:@@ISSNAPSHOT@@:$(issnapshot):' | \
        sed 's:@@GITREV@@:$(gitrev):' > php-awl.spec
	tar czf ../$(package)-$(version).tar.gz \
	    --no-recursion --dereference $(package)-$(version) \
	    $(shell git ls-files |grep -v '.git'|sed -e s:^:$(package)-$(version)/:) \
	    $(shell find $(package)-$(version)/docs/api/ ! -name "phpdoc.ini" ) php-awl.spec
	rm $(package)-$(version)

snapshot: release

clean:
	rm -f built-docs
	-find . -name "*~" -delete
	rm -f php-awl.spec
	
clean-all: clean
	-find docs/api/* ! -name "phpdoc.ini" ! -name ".gitignore" -delete

.PHONY:  all clean release
