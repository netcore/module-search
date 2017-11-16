## Module for easy search implementation
This module was made for easy searching in database.

## Features

- Multiple models per request
- Search through multi-level relations

## Pre-installation

This module is part of Netcore CMS ecosystem and is only functional in a project that has following packages installed:

1. https://github.com/netcore/netcore

### Installation

 1. Require this package using composer
```bash
    composer require netcore/module-search
```

 2. Add SearchableContract and configure searchable models 
```php
    use Illuminate\Database\Eloquent\Model;
    use Modules\Search\Contracts\SearchableContract; 

    class Article extends Model implements SearchableContract {
        ...        
        
        /**
         * Get the config of search module for building queries.
         *
         * @return array
         */
        public function getSearchConfig(): array
        {
            return [
                // Base table colums
                'columns' => [
                    'slug',
                    'author_name',
                    'author_surname',
                ],
                
                // Add constant wheres if necessary
                // Helpful for columns like is_active, is_published etc..
                'wheres' => [
                    'is_active' => true,
                ],
                
                // Search in relations
                'relations' => [
                    'translations' => [
                        'columns' => [
                            'title', 
                            'content',
                            'intro_text',
                        ],
                        'wheres' => [
                            'locale' => app()->getLocale() // search only in translations for current locale
                        ],
                        // Nested relations supported
                        'relations' => [...]
                    ]
                ]
            ];
        }
        
        ...
    }
```

### Searching records

- To find something, you must use search() helper method
```php 
    $page = (int)request('page', 1) ?? 1;

    $results = search()
        ->of(\App\Article::class) // set searchable models (multiple allowed)
        ->setPage($page) // optional - set the current page (1st is by default)
        ->setRecordsPerPage(15) // optional - set records count per page (20 is by default)
        ->withLoggedQueries() // optional - enables query logging
        ->returnAsCollection() // optional - returns collection instead of array
        ->where(\App\Article::class, 'translations.is_active', true) // add additional where to translations relation
        ->where(\App\Article::class, 'is_awesome', true) // add additional where to base model
        ->find('keyword');
        
    dd($results);    
```
