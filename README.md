# A base package for Neos CMS projects

[![Latest Stable Version](https://poser.pugx.org/shel/neos-sqip/v/stable)](https://packagist.org/packages/shel/neos-sqip)
[![Total Downloads](https://poser.pugx.org/shel/neos-sqip/downloads)](https://packagist.org/packages/shel/neos-sqip)
[![License](https://poser.pugx.org/shel/neos-sqip/license)](https://packagist.org/packages/shel/neos-sqip)

Description
-----------

This package provides a fusion object for Neos CMS to render svg image placeholders based on the **SQIP** technique.
They can be used to show a blurry or abstract version of an image while the real image is still lazy loading.

Learn more about **SQIP** is a method to generate SVG based placeholders for images here:
* Original node.js version [sqip](https://github.com/technopagan/sqip) used by this package. 
* The [go variant](https://github.com/denisbrodbeck/sqip) which is also supported via configuration.

**Attention:** the node.js based `sqip` is not released in version 1 yet. 
Therefore breaking changes might happen when you use the global binary and you might need to adjust
the arguments in the settings or wait for an update of this package.

Installation
------------

Requires npm (node.js) to work out of the box, although binaries can also be installed manually without it.

    composer require shel/neos-sqip

Ensure the image manipulation library `sqip` is installed globally. 

Alternatively install them using `npm`:

#### Globally

    npm install -g sqip

#### Locally

    npm install --prefix Packages/Plugins/Shel.Neos.Sqip/Resources/Private/Library
    
How to use
----------

Use the provided fusion object `Shel.Neos.Sqip:ImageTag` to render an image with placeholder and use
the lazy image loader [layzr](https://github.com/callmecavs/layzr.js) to lazy load the actual images.
This object already provides the necessary attributes to make `layzr` work out of the box.

You can also use the provided `Shel.Neos.Sqip:SqipImage` fusion object to just render the SVG data which you can
put into the `src` attribute of an `img` tag or in a inline style as background image.

The fusion object `Shel.Neos.Sqip:SqipCaseRenderer` provides a renderer for your `src` attribute which checks 
if the user is in the backend and then decides to render the original image uri instead of the SQIP image. 

You can use this for example to modify the [Carbon.Image:Tag](https://github.com/CarbonPackages/Carbon.Image) object like this:

    prototype(Carbon.Image:Tag) {
        attributes {
            src >
            src = Shel.Neos.Sqip:SqipCaseRenderer {
                asset = ${asset}
            }
            srcset >
            data-normal = Carbon.Image:ImageUri
            data-srcset = Carbon.Image:Srcset
        }
    }

#### Compatible image formats

This package was tested with pngs, jpegs and svgs.
Possibly other formats work well too.
    
Configuration
-------------

#### Default options

    Shel:
      Neos:
        Sqip:
          useGlobalBinary: false # use globally installed binaries
          globalBinaryPath: ''
          library: 'sqip'
          binaryPath: '.bin/sqip'
          arguments: "${'node ' + binaryPath + ' -n ' + numberOfPrimitives + ' -m ' + mode + ' -b ' + blur + ' ' + file}"
          parameters:
            # Customize the number of primitive SVG shapes (default=8) to influence bytesize or level of detail
            numberOfPrimitives: 8
            # Specify the type of primitive shapes that will be used to generate the image
            # 0=combo, 1=triangle, 2=rect, 3=ellipse, 4=circle, 5=rotatedrect, 6=beziers, 7=rotatedellipse, 8=polygon
            mode: 0
            # Set the gaussian blur
            blur: 12
    
#### Using the go variant

The go version is faster than the node.js version and has less dependencies but currently doesn't provide the blur parameter.
Both binaries have the same cli output and therefore work fine.

1. Install the `sqip` binary like described in their [instructions](https://github.com/denisbrodbeck/sqip).
2. Override the package settings in your own package like this:

        Shel:
          Neos:
            Sqip:
              useGlobalBinary: true
              globalBinaryPath: '/path/to/your/go/bin/' # Adapt this to your system
              arguments: "${binaryPath + ' -n ' + numberOfPrimitives + ' -mode ' + mode + ' ' + file}"
              parameters:
                # Customize the number of primitive SVG shapes (default=8) to influence bytesize or level of detail
                numberOfPrimitives: 8
                # Specify the type of primitive shapes that will be used to generate the image
                # 0=combo, 1=triangle, 2=rect, 3=ellipse, 4=circle, 5=rotatedrect, 6=beziers, 7=rotatedellipse, 8=polygon
                mode: 0

#### Caching

The generated SVGs will be cached in the filesystem as strings. 

To speed the Cache up you can switch to use Redis like this in your `Caches.yaml`:

    ShelNeosSqip_ImageCache:
      backend: Neos\Cache\Backend\RedisBackend
      backendOptions:
        database: 0

You can make the cache persistent to keep cached images when temporary caches are flushed:

    ShelNeosSqip_ImageCache:
      persistent: true
