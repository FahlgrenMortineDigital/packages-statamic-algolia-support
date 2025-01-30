# Statamic Algolia Support

Add helpful support classes for working with the Algolia driver in Statamic applications.

# Installation

```shell
$ composer require fahlgrendigital/packages-statamic-algolia-support
```

### Publish

```shell
$ php artisan vendor:publish --tag=statamic-algolia-support-config
```

# Usage

This package focuses on a few different areas of support for the Algolia driver in Statamic applications:

* Transformers
* Index building
* Index importing

## Transformers

This package offers three transformers designed for handling complex data types in Statamic.

* CollectionTransformer
* DateTransformer
* MarkupTransformer

**CollectionTransformer**

This transformer currently transformers a collection object into the handle. A common use case for this field
is configuring it as a facet in your Algolia index for filtering.

**DateTransformer**

This transformer currently transformers a date object into a formatted date string using the following format: `Y-m-d H:i:s`.

**MarkupTransformer**

This transformer supports the following field types within Statamic:

* Bard
* Textarea

## Indexes

This package offers a simple index builder for creating Algolia indexes in your Statamic application. You can configure
a laravel filesystem disk to store your indexes in. This is useful for keeping your indexes in sync across multiple
algolia environments. Statamic will automagically handle saving CRUD operations for configured index sources in Algolia,
and the index builder is designed to handle cases where you want to do an entire index update within Algolia.
