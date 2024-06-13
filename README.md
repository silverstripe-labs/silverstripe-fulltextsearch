# FullTextSearch module

[![CI](https://github.com/silverstripe/silverstripe-fulltextsearch/actions/workflows/ci.yml/badge.svg)](https://github.com/silverstripe/silverstripe-fulltextsearch/actions/workflows/ci.yml)

Adds support for fulltext search engines like Sphinx and Solr to Silverstripe CMS.
Compatible with PHP 7.2

## Important notes when upgrading to fulltextsearch 3.7.0+

There are some significant changes from previous versions:

Draft content will no longer be automatically added to the search index.  This new behaviour was previously an
opt-in behaviour that was enabled by adding the following line to a search index:

```
$this->excludeVariantState([SearchVariantVersioned::class => Versioned::DRAFT]);
```

A new `canView()` check against an anonymous user (i.e. someone not logged in) and a `ShowInSearch` check is now
performed by default against all records (DataObjects) before being added to the search index, and also before being
shown in search results. This may mean that some records that were previously being indexed and shown in search results
will no longer appear due to these additional checks.

These additional checks have been added with data security in mind, and it's assumed that records failing these
checks probably should not be indexed in the first place.

# Enable indexing of draft content:

You can index draft content with the following yml configuration:

```
SilverStripe\FullTextSearch\Search\Services\SearchableService:
  variant_state_draft_excluded: false
```

However, when set to false, it will still only index draft content when a DataObject is in a published state, not a
draft-only or modified state.  This is because it will still fail the new anonymous user `canView()` check in
`SearchableService::isSearchable()` and be automatically deleted from the index.

If you wish to also index draft content when a DataObject is in a draft-only or a modified state, then you'll need
to also configure `SearchableService::indexing_canview_exclude_classes`.  See below for instructions on how to do this.

# Disabling the anonymous user canView() pre-index check

You can apply configuration to remove the new pre-index `canView()` check from your DataObjects if it is not necessary,
or if it impedes expected functionality (e.g. for sites where users must authenticate to view any content). This will
also disable the check for descendants of the specified DataObjects. Ensure that your implementation of fulltextsearch
is correctly performing a `canView()` check at query time before disabling the pre-index check, as this may result in
leakage of private data.

```
SilverStripe\FullTextSearch\Search\Services\SearchableService:
  indexing_canview_exclude_classes:
    - Some\Org\MyDataObject
    # This will disable the check for all pagetypes:
    - SilverStripe\CMS\Model\SiteTree
```

You can also use the `updateIsSearchable` extension point on `SearchableService` to modify the result of the method
after the `ShowInSearch` and `canView()` checks have run. 

It is highly recommend you run a [solr_reindex](https://github.com/silverstripe/silverstripe-fulltextsearch/blob/3/docs/en/03_configuration.md#solr-reindex)
on your production site after upgrading from 3.6 or earlier to purge any old data that should no longer be in the search index.

These additional check can have an impact on the reindex performance due to additional queries for permission checks.
If your site also indexes content in files, such as pdf's or docx's, using the [text-extraction](https://github.com/silverstripe/silverstripe-textextraction)
module which is fairly time-intensive, then the relative performance impact of the `canView()` checks won't be as noticeable.

## Details on filtering before adding content to the solr index
- `SearchableService::isIndexable()` check in `SolrReindexBase`. Used when indexing all records during Solr reindex.
- `SearchableService::isIndexable()` check in `SearchUpdateProcessor`. Used when indexing single records during
`DataObject->write()`.

## Details on filtering when extracting results from the solr index
- `SearchableService::isViewable()` check in `SolrIndex`. This will often be used in CWP implementations that use the
`CwpSearchEngine` class, as well as most custom implementations that call `MySearchIndex->search()`
- `SearchableService::isViewable()` check in `SearchForm`. This will be used in solr implementations where a
`/SearchForm` url is used to display search results.
- Some implementations will call `SearchableService::isViewable()` twice. If this happens then the first call will be
cached in memory so there is virtually no performance penalty calling it a second time.
- If your implementation is very custom and does not subclass nor make use of either `SolrIndex` or `SearchForm`, then
it's recommended you update your implementation to call `SearchableService::isViewable()`.

## Requirements

* Silverstripe 4.0+

**Note:** For Silverstripe 3.x, please use the [2.x release line](https://github.com/silverstripe/silverstripe-fulltextsearch/tree/2).

## Documentation

For pure Solr docs, check out [the Solr 4.10.4 guide](https://archive.apache.org/dist/lucene/solr/ref-guide/apache-solr-ref-guide-4.10.pdf).

See [the docs](/docs/en/00_index.md) for configuration and setup, or for the quick version see [the quick start guide](/docs/en/01_getting_started.md#quick-start).

For details of updates, bugfixes, and features, please see the [changelog](CHANGELOG.md).

## TODO

* Get rid of includeSubclasses - isn't actually used in practice, makes the codebase uglier, and ClassHierarchy can be
used at query time for most of the same use cases

* Fix field referencing in queries. Should be able to do `$query->search('Text', 'Content')`, not
`$query->search('Text', SiteTree::class . '_Content')` like you have to do now

    - Make sure that when field exists in multiple classes, searching against bare fields searches all of them

    - Allow searching against specific instances too

* Make fields restrictable by class in an index - 'SiteTree#Content' to limit fields to a particular class,
maybe 'Content->Summary' to allow calling a specific method on the field object to get the text

* Allow following user relationships (Children.Foo for example)

* Be clearer about what happens with relationships to stateful objects (e.g. Parent.Foo where Parent is versioned)

* Improvements to SearchUpdater

     - Make it work properly when in-between objects (the A in A.B.Foo) update

     - Allow user logic to cause triggering reindex of documents when field is user generated

* Add generic APIs for spell correction, file text extraction and snippet generation
