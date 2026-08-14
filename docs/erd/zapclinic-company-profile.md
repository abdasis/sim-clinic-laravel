# Zapclinic Company Profile — ERD

Diturunkan dari landing page `https://zapclinic.com/` (2026-08-14). Hanya entitas yang teramati di UI. Landing page company profile — tidak ada submit form, jadi model data bersifat read-only display.

```mermaid
erDiagram
    TREATMENT {
        int id PK
        string slug UK
        string title
        text description
        string image_url
        boolean is_featured
        string featured_label
        string status
    }

    TREATMENT_CATEGORY {
        int id PK
        string name
        string slug UK
    }

    TREATMENT_TAG {
        int id PK
        string name
        string slug UK
    }

    PROMO {
        int id PK
        string title
        text description
        string image_url
        date valid_until
        string status
    }

    BRAND {
        int id PK
        string name
        text description
        string logo_url
        string external_url
    }

    TESTIMONIAL {
        int id PK
        text quote
        string author_name
        int since_year
        string author_avatar_url
    }

    OUTLET {
        int id PK
        string name
        string address
        string city
        string phone
        decimal latitude
        decimal longitude
        string status
    }

    ARTICLE {
        int id PK
        string slug UK
        string title
        text excerpt
        string cover_image_url
        date published_at
        string status
    }

    STORE_PRODUCT {
        int id PK
        string name
        text description
        string image_url
        int price
        string category
        string status
    }

    CART {
        int id PK
        int user_id FK
    }

    CART_ITEM {
        int id PK
        int cart_id FK
        int store_product_id FK
        int quantity
    }

    TREATMENT }o--o{ TREATMENT_CATEGORY : "has"
    TREATMENT }o--o{ TREATMENT_TAG : "tagged"
    CART ||--o{ CART_ITEM : "contains"
    CART_ITEM }o--|| STORE_PRODUCT : "references"
```

Catatan:
- `TREATMENT_CATEGORY` / `TREATMENT_TAG` dimodel many-to-many karena satu treatment menampilkan multiple tag (contoh: "rejuvenation", "brightening", "oily skin"). Hubungannya junction table implisit.
- `OUTLET` diturunkan dari klaim "100+ outlet" + menu "Lokasi" (`/locations`) — tidak ada detail field teramati, kolom adalah perkiraan standar untuk pencarian lokasi.
- `STORE_PRODUCT`, `CART`, `CART_ITEM` diturunkan dari E-Store (`/store`) + ikon cart dengan badge count di header.
- `ARTICLE` diturunkan dari menu "Artikel" (`/articles`) — field perkiraan untuk blog company profile.
- `PROMO` field `valid_until` dari teks "Sampai 15 September 2026" pada hero.
- Widget chat (Qiscus) adalah layanan eksternal, tidak dimodel di sini.