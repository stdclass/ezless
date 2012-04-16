# eZ Less

The eZ Less extension combines the powerful eZ Publish content management
system with the awesome LESS CSS pre-processor.

## Dependencies

eZ Less makes use of lessphp and less.js.

Current versions of these libraries are :

 * lessphp : 0.3.4
 * less.js : 1.3.0

## Compatibility

eZ Less should be compatible with Twitter Bootstrap 2.0.2

## Get the sources from github | Installation

If you want to install ezless from github then you'll need to manually pull
lessphp since it's provided as a submodule.


```bash

cd /path/to/your/ezpublish/install
git clone https://github.com/stdclass/ezless extension/ezless
cd extension/ezless
git submodule init
git submodule update


```

Then you know what to do : activate the extension, regenerate autoloads, ...

