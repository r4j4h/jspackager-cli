jspackager-cli
==============


About
------------

Command Line Interface for [r4j4h/jspackager-test](https://github.com/r4j4h/jspackager-test).


Typical Usage
------------

From the repository root, execute `php src/jspackager.php --help` to get started.

```
Available commands:
  clear-folders     Clear compiled files and manifests in given folder(s).
  compile-files     Compiled given file(s).
  compile-folders   Compile files in given folder(s).
  resolve-files     Resolve a file's dependencies.
```

You probably want to use `-vvv` at least at first to get a good idea of what's going on.



How you want to use this really depends on how you lay out your scripts and want them batched together.

If you have a folder of main files then you might find these two argument sets useful:

`php vendor/bin/jspackager.php compile-folders js/`

`php vendor/bin/jspackager.php clear-folders js/`

But you may want more fine grained control:

`php vendor/bin/jspackager.php compile-files js/main.js js/another-file.js`


Require Format
------------

The require format is annotation driven and comes from jspackager-test.

As a rough overview if you include

`@require <relative path to another file>`

inside a file. It will define a dependency link.

If this file is then run through the dependency resolver, any combination of these @require's in files will be parsed and an ordered array returned that will provide things in dependency order with minimal re-use. It is oriented for browser use but can really work for anything.

It is streamlined for the end user's browser - no boilerplate code required to "run" it. At the expense of being in the global scope.

This works well with global namespaces.

I want to add in asynchronous support, and script rewriting, as auto wrapping in CommonJS or AMD format or ES6 is not a huge ordeal.

