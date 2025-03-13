# FeexpayPhp

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Total Downloads][ico-downloads]][link-downloads]

**Note:** Replace ```Feexpay``` ```LOUGBEGNON``` ```https://feexpay.me``` ```contact@feexpay.me``` ```Feexpay``` ```FeexpayPhp``` ```Php sdk of Feexpay - Online payment solution by credit card and mobile money``` with their correct values in [README.md](README.md), [CHANGELOG.md](CHANGELOG.md), [CONTRIBUTING.md](CONTRIBUTING.md), [LICENSE.md](LICENSE.md) and [composer.json](composer.json) files, then delete this line. You can run `$ php prefill.php` in the command line to make all replacements at once. Delete the file prefill.php as well.

This is where your description should go. Try and limit it to a paragraph or two, and maybe throw in a mention of what
PSRs you support to avoid any confusion with users and contributors.

## Structure

If any of the following are applicable to your project, then the directory structure should follow industry best practices by being named the following.

```

src/
vendor/
```


## Install

Via Composer

``` bash
$ composer require Feexpay/FeexpayPhp
```

## Usage

There are two ways to use the php sdk. 

Use of the SDK functions: 


``` php
$skeleton = new Feexpay\FeexpayPhp\FeexpayClass("shop's id", "token key API", "shop's id", "callback_url", "mode (LIVE, SANDBOX)");
$response = $skeleton->paiementLocal("amount", "phone_number", "network (MTN, MOOV)", "Jon Doe","jondoe@gmail.com");
$status = $skeleton->getPaiementStatus($response);

var_dump($status);
```

Add a payment button on the user interface:

```
<div id='button_payee'></div>
@php
    $price = 50;
    $id= "shop's id";
    $token= "token key api";
    $callback_url= 'https://www.google.com';
    $mode='LIVE';
    $feexpayclass = new Feexpay\FeexpayPhp\FeexpayClass($id, $token, $callback_url, $mode);
    $result = $feexpayclass->init($price, "button_payee")
@endphp
```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email contact@feexpay.me instead of using the issue tracker.

## Credits

- [Feexpay][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/Feexpay/FeexpayPhp.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/Feexpay/FeexpayPhp/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/Feexpay/FeexpayPhp.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/Feexpay/FeexpayPhp.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/Feexpay/FeexpayPhp.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/Feexpay/FeexpayPhp
[link-travis]: https://travis-ci.org/Feexpay/FeexpayPhp
[link-scrutinizer]: https://scrutinizer-ci.com/g/Feexpay/FeexpayPhp/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/Feexpay/FeexpayPhp
[link-downloads]: https://packagist.org/packages/Feexpay/FeexpayPhp
[link-author]: https://github.com/LOUGBEGNON
[link-contributors]: ../../contributors
