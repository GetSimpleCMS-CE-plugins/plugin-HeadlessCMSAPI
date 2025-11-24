# Headless CMS API for GetSimple CMS

A powerful REST API plugin that transforms GetSimple CMS into a headless CMS with full SimpleBlog support. Access your pages, menu structure, blog posts, and more through clean JSON endpoints.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![GetSimple](https://img.shields.io/badge/GetSimple-3.3%2B-green.svg)](http://get-simple.info/)
[![PHP](https://img.shields.io/badge/PHP-7.0%2B-purple.svg)](https://php.net)

## Features

- 🚀 **RESTful API** - Clean JSON responses for all content
- 🔐 **Secure Authentication** - API key generation with header/query support
- 📝 **CMS Integration** - Full access to pages, menu, navigation, and components
- 📰 **SimpleBlog Support** - Automatic detection and endpoints for blog posts, categories, and comments
- 🌍 **CORS Support** - Easy configuration for cross-origin requests
- 🔍 **Search Functionality** - Search through pages and blog posts
- 📊 **Pagination** - Built-in pagination for all list endpoints
- 🛠️ **Database Compatibility** - Automatic adaptation to different SimpleBlog versions

## Installation

1. Download the `headlessCMS.php` file
2. Upload it to your GetSimple CMS `/plugins/` directory
3. Navigate to **Plugins** in your GetSimple admin panel
4. Activate the **Headless CMS API** plugin

## Quick Start

After installation, access the API configuration at:
https://your-site.com/admin/load.php?id=headlessCMS

text

### Basic Usage

// Get all pages
fetch('https://your-site.com/?api=pages')
.then(res => res.json())
.then(data => console.log(data));

// Get single page
fetch('https://your-site.com/?api=page&slug=about')
.then(res => res.json())
.then(data => console.log(data));

text

## API Endpoints

### CMS Endpoints

| Endpoint | Description | Parameters |
|----------|-------------|------------|
| `?api=info` | API information and available endpoints | - |
| `?api=pages` | Get all pages | `limit`, `offset`, `sort`, `order`, `include_private` |
| `?api=page&slug=SLUG` | Get single page | `slug` (required) |
| `?api=menu` | Get menu structure | - |
| `?api=navigation` | Get hierarchical navigation | - |
| `?api=search&q=QUERY` | Search pages | `q` (required) |
| `?api=components` | Get components | `name` (optional) |
| `?api=settings` | Get site settings | - |

### SimpleBlog Endpoints

*Available when SimpleBlog plugin is installed*

| Endpoint | Description | Parameters |
|----------|-------------|------------|
| `?api=blog/posts` | Get all blog posts | `limit`, `offset` |
| `?api=blog/post&slug=SLUG` | Get single post | `slug` (required) |
| `?api=blog/categories` | Get all categories | - |
| `?api=blog/category&slug=SLUG` | Get posts by category | `slug` (required), `limit`, `offset` |
| `?api=blog/recent` | Get recent posts | `limit` (default: 5) |
| `?api=blog/search&q=QUERY` | Search blog posts | `q` (required) |
| `?api=blog/comments` | Get comments | `post_id` (optional) |

## Authentication

### Enable Authentication

1. Go to plugin settings in admin panel
2. Check **Require Authorization**
3. Copy your generated API key
4. Use the key in your requests

### Usage with Authentication

**Query Parameter:**
https://your-site.com/?api=pages&key=YOUR_API_KEY

text

**Header (recommended):**
fetch('https://your-site.com/?api=pages', {
headers: {
'X-API-Key': 'YOUR_API_KEY'
}
})

text

## Configuration

### CORS Settings

Enable CORS in plugin settings to allow cross-origin requests from your frontend application.

### API Key Management

- Generate secure 64-character API keys
- Regenerate keys anytime (old keys become invalid)
- Store keys in `/data/other/headless_api_config.json`

## Response Format

### Success Response
{
"success": true,
"count": 10,
"total": 45,
"data": [...]
}

text

### Error Response
{
"error": "Error message here"
}

text

## Examples

### PHP with cURL
$ch = curl_init('https://your-site.com/?api=pages');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
'X-API-Key: YOUR_API_KEY'
]);
$response = curl_exec($ch);
$data = json_decode($response, true);

text

### JavaScript with Axios
import axios from 'axios';

const api = axios.create({
baseURL: 'https://your-site.com',
headers: {
'X-API-Key': 'YOUR_API_KEY'
}
});

// Get pages
const pages = await api.get('/?api=pages');

// Get blog posts
const posts = await api.get('/?api=blog/posts&limit=10');

text

### React Hook Example
import { useState, useEffect } from 'react';

function usePages() {
const [pages, setPages] = useState([]);
const [loading, setLoading] = useState(true);

useEffect(() => {
fetch('https://your-site.com/?api=pages')
.then(res => res.json())
.then(data => {
setPages(data.pages);
setLoading(false);
});
}, []);

return { pages, loading };
}

text

## Requirements

- GetSimple CMS 3.3+
- PHP 7.0 or higher
- SQLite3 extension (for SimpleBlog support)

## Compatibility

- ✅ GetSimple CMS 3.3+
- ✅ SimpleBlog plugin (all versions)
- ✅ PHP 7.0 - 8.x

## Changelog

### Version 1.0
- Added automatic SimpleBlog database structure detection
- Fixed compatibility with different SimpleBlog versions
- Improved error handling for missing columns
- Added SimpleBlog integration
- Added blog endpoints (posts, categories, comments)
- Improved API documentation
- Initial release
- CMS endpoints (pages, menu, navigation, search)
- API key authentication
- CORS support

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Author

Your Name - [Your Website](https://your-website.com)

## Support

- 🐛 [Report Issues](https://github.com/yourusername/getsimple-headless-api/issues)
- 📖 [Documentation](https://github.com/yourusername/getsimple-headless-api/wiki)
- 💬 [Discussions](https://github.com/yourusername/getsimple-headless-api/discussions)

## Acknowledgments

- Built for [GetSimple CMS](http://get-simple.info/)
- Compatible with [SimpleBlog](https://github.com/GetSimpleCMS-CE-plugins/plugin-simpleBlog) plugin

---

⭐ If you find this plugin useful, please consider starring the repository!
