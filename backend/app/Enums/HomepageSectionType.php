<?php

namespace App\Enums;

enum HomepageSectionType: string
{
    case Hero = 'HERO';
    case Personalization = 'PERSONALIZATION';
    case ValueProps = 'VALUE_PROPS';
    case MiniBanners = 'MINI_BANNERS';
    case Categories = 'CATEGORIES';
    case BestSellers = 'BEST_SELLERS';
    case FeaturedCategories = 'FEATURED_CATEGORIES';
    case EditorsPicks = 'EDITORS_PICKS';
    case SplitCollage = 'SPLIT_COLLAGE';
    case ImageBanner = 'IMAGE_BANNER';
    case TextBanner = 'TEXT_BANNER';
    case VideoBanner = 'VIDEO_BANNER';
    case ProductCarousel = 'PRODUCT_CAROUSEL';
    case InstagramDiaries = 'INSTAGRAM_DIARIES';
    case TrendingStyles = 'TRENDING_STYLES';
    case IconInfo = 'ICON_INFO';
    case VisitStores = 'VISIT_STORES';
    case StoreLocations = 'STORE_LOCATIONS';
    case Testimonials = 'TESTIMONIALS';
    case RichText = 'RICH_TEXT';
    case Faq = 'FAQ';
    case VendorStores = 'VENDOR_STORES'; // kept for compatibility
}
