# Akeneo Rekognition Bundle - Click And Mortar

![Akeneo Rekognition Bundle Logo](akeneo-rekognition-bundle-logo.png)

`Akeneo Rekognition Bundle` allows to retrieve objects and texts
detected with [AWS Rekognition](https://aws.amazon.com/rekognition/) 
(using [rekognition-php](https://github.com/ClickAndMortar/rekognition-php)) from a product model image and to store them into this product model.

![Akeneo Rekognition Bundle in 3 steps](img/akeneo-rekognition-bundle-in-3-steps.png)

# Requirements

|                                     | Version |
| ----------------------------------- | ------- |
| PHP                                 | `>=7.1` |
| [Akeneo](https://www.akeneo.com/)   | `>=2.3` |

An AWS account is also required as
[AWS Rekognition](https://aws.amazon.com/rekognition/)
will be used.

# Installation

## Download the Bundle

```console
$ composer require clickandmortar/akeneo-rekognition-bundle
```

## Enable the Bundle

Enable the bundle by adding it to the list of registered bundles
in the `app/AppKernel.php` file of your project:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            // ...
            new ClickAndMortar\AkeneoRekognitionBundle\ClickAndMortarAkeneoRekognitionBundle(),
        ];

        // ...
    }

    // ...
}
```

# Configuration

## Configure credentials

Before using `Akeneo Rekognition Bundle `, 
[set credentials to make requests to Amazon Web Services](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials.html).

## Import attributes

Import new attributes to store data from `Rekognition`:

```
php bin/console akeneo:batch:job -c "{\"filePath\":\"vendor/clickandmortar/akeneo-rekognition-bundle/Resources/fixtures/attributes.csv\"}" csv_attributes_import
```

## Add new attributes to family

[Add new attributes to family](https://help.akeneo.com/articles/manage-your-families.html#manage-attributes-in-a-family)

## Edit a family variant

[Edit a family variant](https://help.akeneo.com/articles/manage-your-families.html#edit-a-family-variant)

## Create job
```
php bin/console akeneo:batch:create-job internal add_rekognition_data mass_edit add_rekognition_data '{}' 'Add Rekognition Data'
```

# Usage

## Run job

The following line will process all "1st variant Color" (See
[What about products variants](https://help.akeneo.com/articles/what-about-products-variants.html))
with image and add data from Rekognition to the variant.

```
php bin/console akeneo:batch:job add_rekognition_data
```

## Mass edit

From product models list:
- Check the ones that need to be processed.
- Click "Mass edit".
- Click "Add Rekognition Data".
- Click "Next", "Next", then "Confirm".
- Check on dashboard that operation has status `Completed`.

Open product models that were previously checked.
They now have attributes filled with Rekognition data.

# Roadmap

- [ ] Handle locale (currently only storing in `fr_FR` locale)
- [ ] Add fields to store more information provided by Rekognition
- [ ] Add `composer post install` to avoid to play some configuration commands
manually
- [ ] Find a way to use environment variables with php-fpm
(credentials AWS) for docker
- [ ] Automate detection of entity level holding pictures
(product model with parent only for the moment)
