# jupitern/docx
#### PHP Docx tolls.

- docx "mail merge" template engine
- merge docx files on one file (with or without page breaks)

## Requirements

PHP 8.3 or higher.

## Installation

Pull the package via composer.

```javascript
composer require jupitern/docx
```

## Usage
```php

// Docx template engine. fill placeholders on docx file
\Jupitern\Docx\Docx::instance()
			->setTemplate('template.docx')
			->setData(['{name}' => 'john doe', '{address}' => 'at the end of the road'])
			->save('result.docx');


// merge two docx without page break
\Jupitern\Docx\DocxMerge::instance()
	->addFiles(['file1.docx', 'file2.docx'])
	->save('result.docx', \Jupitern\Docx\DocxMerge::BREAK_NONE);

// merge two docx with simple page break. header and footer from first document are used
\Jupitern\Docx\DocxMerge::instance()
	->addFiles(['file1.docx', 'file2.docx'])
	->save('result.docx', \Jupitern\Docx\DocxMerge::BREAK_PAGE);

// merge two docx with section and page break. header and footer from each document remain intact
\Jupitern\Docx\DocxMerge::instance()
	->addFiles(['file1.docx', 'file2.docx'])
	->save('result.docx', \Jupitern\Docx\DocxMerge::BREAK_SECTION);

```

## Contributing

 - welcome to discuss a bugs, features and ideas.

## License

jupitern/docx is release under the MIT license.

You are free to use, modify and distribute this software
