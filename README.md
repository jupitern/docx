# jupitern/docx
#### PHP Docx tolls.

- docx template system
- merge docx files on one file
- optionally add page break between merged files

## Requirements

PHP 8.3 or higher.

## Installation

Pull the package via composer.

```javascript
composer require jupitern/docx
```

## Usage
```php

// Docx template
$docx = \Jupitern\Docx\Docx::instance()
			->setTemplate('template.docx')
			->setData(['{name}' => 'john doe', '{address}' => 'at the end of the road'])
			->save('result.docx');

// Merge Docx files
$docxMerge = \Jupitern\Docx\DocxMerge::instance()
    // add array of files to merge
	->addFiles(['file1.docx', 'file2.docx'])
    // output filepath and pagebreak param
	->save('result.docx', true);

```

## Contributing

 - welcome to discuss a bugs, features and ideas.

## License

jupitern/docx is release under the MIT license.

You are free to use, modify and distribute this software
