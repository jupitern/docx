# jupitern/docx
#### PHP Docx tolls.

- docx template system
- merge docx files on one file
- optionally add page break between merged files

## Requirements

PHP 5.4 or higher.

## Installation

Include jupitern/docx in your project, by adding it to your composer.json file.
```javascript
{
    "require": {
        "jupitern/docx": "1.*"
    }
}
```

## Usage
```php
$dir = 'C:\\www\\docx\\';

// Docx template
$docx = \Jupitern\Docx\Docx::instance()
			->setTemplate($dir.'template.docx')
			->setData(['{name}' => 'john doe', '{address}' => 'at the end of the road'])
			->save($dir.'result.docx');

// Merge Docx files
$docxMerge = \Jupitern\Docx\DocxMerge::instance()
    // add array of files to merge
	->addFiles([$dir.'file1.docx', $dir.'file2.docx'])
    // output filepath and pagebreak param
	->save($dir.'result.docx', true);

```

## Contributing

 - welcome to discuss a bugs, features and ideas.

## License

jupitern/docx is release under the MIT license.

You are free to use, modify and distribute this software
