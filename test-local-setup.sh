#!/bin/bash

echo "🧪 Testing Veldrin Store Local Development Environment"
echo "=================================================="

# Check if Docker containers are running
echo "1. Checking Docker containers..."
if docker compose ps | grep -q "Up"; then
    echo "✅ Docker containers are running"
else
    echo "❌ Docker containers are not running"
    echo "   Run: docker compose up -d"
    exit 1
fi

# Check if WordPress is accessible
echo "2. Testing WordPress accessibility..."
if curl -s -o /dev/null -w "%{http_code}" http://localhost:8000 | grep -q "200"; then
    echo "✅ WordPress is accessible at http://localhost:8000"
else
    echo "❌ WordPress is not accessible"
    exit 1
fi

# Check if theme assets are built
echo "3. Checking theme assets..."
if [ -f "wp-content/themes/veldrin/css/main.min.css" ]; then
    echo "✅ CSS assets are built"
else
    echo "❌ CSS assets are missing"
    echo "   Run: cd wp-content/themes/veldrin && npx gulp development"
fi

if [ -f "wp-content/themes/veldrin/js/main.min.js" ]; then
    echo "✅ JavaScript assets are built"
else
    echo "❌ JavaScript assets are missing"
    echo "   Run: cd wp-content/themes/veldrin && npx gulp development"
fi

# Check if theme is active
echo "4. Checking theme status..."
if curl -s http://localhost:8000 | grep -q "Veldrin\|Ringbearer"; then
    echo "✅ Veldrin theme appears to be active"
else
    echo "⚠️  Veldrin theme may not be active"
    echo "   Visit http://localhost:8000/wp-admin and activate the theme"
fi

# Check database connection
echo "5. Testing database connection..."
if docker compose exec db mysql -u store -pstore -e "SELECT 1;" 2>/dev/null; then
    echo "✅ Database connection successful"
else
    echo "❌ Database connection failed"
fi

echo ""
echo "🎉 Local development environment is ready!"
echo ""
echo "📋 Next steps:"
echo "   • Visit http://localhost:8000 to see your site"
echo "   • Visit http://localhost:8000/wp-admin to access WordPress admin"
echo "   • Run 'cd wp-content/themes/veldrin && npx gulp' to watch for changes"
echo ""
echo "🔧 Useful commands:"
echo "   • docker compose logs wordpress    # View WordPress logs"
echo "   • docker compose logs db           # View database logs"
echo "   • docker compose restart           # Restart containers"
echo "" 