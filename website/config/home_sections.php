<?php
return json_decode(<<<'JSON'
{
  "tradition": {
    "eyebrow": "Our Tradition",
    "heading": "A Taste of Italy,\nMade with Love",
    "description": "At Mamma Mia Cucina, we bring the heart of Italy to your table with authentic recipes made from premium ingredients and crafted with traditional Italian craftsmanship.\nFrom classic cakes to delicate pastries, every bite is a celebration of our heritage.",
    "image_alt": "Caprese cake with Italian ingredients",
    "image": "assets/images/mmc/tradition-caprese.png"
  },
  "baking": {
    "eyebrow": "Authentic Recipes",
    "heading": "The Art of Italian Baking",
    "description": "",
    "image_alt": "Italian cassata cake and chocolate pastries",
    "image": "assets/images/mmc/baking-background.png",
    "features": [
      {
        "heading": "Premium Ingredients",
        "description": "We use only the finest ingredients sourced for exceptional taste and quality."
      },
      {
        "heading": "Traditional Craftsmanship",
        "description": "Time-honored recipes and techniques passed down through generations."
      },
      {
        "heading": "Frozen for Freshness",
        "description": "Ready to bake and enjoy anytime while maintaining perfect freshness."
      }
    ]
  }
}
JSON, true);
