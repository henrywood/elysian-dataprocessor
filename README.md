# Elysian DataProcessor

[![Latest Version on Packagist](https://img.shields.io/packagist/v/elysian/dataprocessor.svg?style=flat-square)](https://packagist.org/packages/elysian/dataprocessor)
[![Tests](https://img.shields.io/github/actions/workflow/status/elysian/dataprocessor/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/elysian/dataprocessor/actions/workflows/tests.yml)
[![PHP Version Require](https://img.shields.io/packagist/php-v/elysian/dataprocessor?style=flat-square)](https://packagist.org/packages/elysian/dataprocessor)

A high-performance PHP library for importing and exporting large datasets with cloud storage support, automatic chunking, Swoole coroutines, and memory-efficient generators. Built on top of OpenSpout for maximum performance and minimal memory usage.

## Features

- ⚡ **High Performance**: Process millions of rows with minimal memory usage
- ☁️ **Cloud Storage**: Native support for AWS S3, Google Cloud Storage, Azure Blob Storage
- 🔄 **Auto Chunking**: Automatically splits large files into smaller chunks
- 🚀 **Swoole Support**: Background processing with Swoole coroutines for enhanced performance
- 🧠 **Memory Efficient**: Uses PHP generators to handle large datasets
- 📁 **Multiple Formats**: Excel (XLSX), CSV, ODS support
- ✅ **Data Validation**: Built-in validation system
- 🎯 **Framework Agnostic**: No Laravel dependency - works with any PHP framework
- 🧪 **Well Designed**: Clean contract-based architecture with extensive examples

## Requirements

- PHP 8.1+
- OpenSpout 4.0+
- Optional: Swoole extension for enhanced performance
- Optional: Cloud storage SDKs (AWS, Google Cloud, Azure)

## Installation

```bash
composer require elysian/dataprocessor
