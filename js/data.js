/**
 * AUTO HUB - Mock Data Store
 * Comprehensive product catalog, vehicle compatibility data, and category definitions
 */

const AUTO_HUB_DATA = {
  // Vehicle Makes and their corresponding Models and Years
  vehicles: {
    "Toyota": {
      models: ["Corolla", "Prius", "Hilux", "Aqua", "Vitz", "Land Cruiser Prado", "Premio", "Allion", "Yaris", "RAV4", "CHR", "Camry"],
      years: ["2026", "2025", "2024", "2023", "2022", "2021", "2020", "2019", "2018", "2017", "2016", "2015", "2014", "2012", "2010"]
    },
    "BMW": {
      models: ["3 Series (F30/G20)", "5 Series (G30)", "X1 (F48)", "X3 (G01)", "X5 (G05)", "7 Series", "1 Series", "4 Series Gran Coupe"],
      years: ["2026", "2025", "2024", "2023", "2022", "2021", "2020", "2019", "2018", "2017", "2016", "2015", "2014"]
    },
    "Honda": {
      models: ["Civic", "Fit / Jazz", "Vezel", "Grace", "CR-V", "Accord", "Insight", "HR-V", "City"],
      years: ["2026", "2025", "2024", "2023", "2022", "2021", "2020", "2019", "2018", "2017", "2016", "2015", "2014", "2012"]
    },
    "Nissan": {
      models: ["Sunny (N16/N17)", "X-Trail (T32)", "Leaf", "Navara D40/NP300", "March / Micra", "Tiida", "Qashqai", "Sylphy"],
      years: ["2026", "2025", "2024", "2023", "2022", "2021", "2020", "2019", "2018", "2017", "2016", "2015", "2014", "2010"]
    },
    "Suzuki": {
      models: ["Alto 800 / K10", "Wagon R (FX/FZ/Stingray)", "Swift", "Spacia", "Hustler", "Baleno", "Jimny", "Every"],
      years: ["2026", "2025", "2024", "2023", "2022", "2021", "2020", "2019", "2018", "2017", "2016", "2015", "2014"]
    },
    "Mitsubishi": {
      models: ["Montero / Pajero", "L200 Sportero", "Lancer", "Outlander PHEV", "Eclipse Cross", "Attrage", "Mirage"],
      years: ["2025", "2024", "2023", "2022", "2021", "2020", "2019", "2018", "2017", "2016", "2015", "2014", "2010"]
    },
    "Hyundai": {
      models: ["Tucson", "Santa Fe", "Grand i10", "Elantra", "Creta", "Ioniq 5", "Sonata", "Kona"],
      years: ["2026", "2025", "2024", "2023", "2022", "2021", "2020", "2019", "2018", "2017", "2016", "2015"]
    },
    "Kia": {
      models: ["Sportage", "Sorento", "Picanto", "Seltos", "Rio", "Cerato / Forte", "Carnival", "EV6"],
      years: ["2026", "2025", "2024", "2023", "2022", "2021", "2020", "2019", "2018", "2017", "2016", "2015"]
    },
    "Mercedes-Benz": {
      models: ["C-Class (W204/W205)", "E-Class (W212/W213)", "A-Class (W177)", "GLC Class", "CLA Coupe", "S-Class"],
      years: ["2026", "2025", "2024", "2023", "2022", "2021", "2020", "2019", "2018", "2017", "2016", "2015"]
    },
    "Audi": {
      models: ["A4", "A3 Sedan", "A6", "Q3", "Q5", "Q7", "TT Coupe"],
      years: ["2025", "2024", "2023", "2022", "2021", "2020", "2019", "2018", "2017", "2016", "2015"]
    }
  },

  // 6 Main Categories
  categories: [
    {
      id: "body-parts",
      name: "Body Parts",
      tagline: "Exterior & body parts",
      description: "Bumpers, fenders, grilles, side mirrors, door handles, headlamp assemblies and trim kits designed for exact OEM fitment.",
      image: "images/categories/body.jpg",
      icon: "fa-car-side",
      subcategories: ["Bumper Assemblies", "Fenders & Hoods", "Side Mirrors", "Door Handles", "Grilles & Emblems", "Tailgate Parts"]
    },
    {
      id: "brake-system",
      name: "Brake System",
      tagline: "Brake pads, discs & more",
      description: "High-performance ceramic brake pads, slotted and ventilated brake discs, brake calipers, master cylinders, and ABS sensors.",
      image: "images/categories/brake.jpg",
      icon: "fa-compact-disc",
      subcategories: ["Front Brake Pads", "Rear Brake Pads", "Brake Discs / Rotors", "Brake Shoes & Drums", "Brake Calipers", "Brake Fluid & Lines"]
    },
    {
      id: "cooling-system",
      name: "Cooling System",
      tagline: "Radiators, fans & parts",
      description: "Aluminum core radiators, high-output cooling fan motors, thermostats, water pumps, heater cores, and coolant expansion tanks.",
      image: "images/categories/cooling.jpg",
      icon: "fa-fan",
      subcategories: ["Radiator Assemblies", "Cooling Fans", "Water Pumps", "Thermostats", "Coolant Reservoirs", "Radiator Caps & Hoses"]
    },
    {
      id: "electrical",
      name: "Electrical",
      tagline: "Batteries, spark plugs & more",
      description: "Maintenance-free starter batteries, iridium spark plugs, high-output alternators, starter motors, and automotive LED lighting kits.",
      image: "images/categories/electrical.jpg",
      icon: "fa-bolt",
      subcategories: ["Car Batteries", "Spark Plugs & Coils", "Alternators", "Starter Motors", "LED Headlights & Bulbs", "Fuses & Relays"]
    },
    {
      id: "engine-parts",
      name: "Engine Parts",
      tagline: "Engine components",
      description: "OEM hydraulic engine mountings, timing belt and chain kits, cylinder head gaskets, piston rings, and valve cover assemblies.",
      image: "images/categories/engine.jpg",
      icon: "fa-cogs",
      subcategories: ["Engine Mounts", "Timing Belt Kits", "Gaskets & Seals", "Pistons & Rings", "Valves & Camshafts", "Oil Pumps"]
    },
    {
      id: "filters",
      name: "Filters",
      tagline: "Oil, air & fuel filters",
      description: "Multi-layer high efficiency oil filters, engine air intake filters, cabin air filters with active carbon, and direct fuel filter units.",
      image: "images/categories/filters.jpg",
      icon: "fa-filter",
      subcategories: ["Engine Air Filters", "Oil Filters", "Cabin AC Filters", "Fuel Filters", "Transmission Filters"]
    }
  ],

  // Products Database
  products: [
    {
      id: 1,
      name: "Toyota Corolla Front Brake Pad Set (Ceramic)",
      slug: "toyota-corolla-front-brake-pad",
      category: "brake-system",
      categoryName: "Brake System",
      brand: "Brembo",
      sku: "BP-TY-1001",
      partNumber: "04465-02220",
      make: "Toyota",
      model: "Corolla",
      yearRange: "2014 - 2023",
      price: 8500,
      oldPrice: 9800,
      discount: 13,
      rating: 4.9,
      reviewsCount: 48,
      inStock: true,
      stockQuantity: 24,
      image: "images/products/brake_pad.jpg",
      gallery: [
        "images/products/brake_pad.jpg",
        "images/products/brake_disc.jpg",
        "images/products/rear_brake_pad.jpg"
      ],
      isFeatured: true,
      isBestSeller: true,
      isOnSale: true,
      isNew: false,
      shortDescription: "Ultra-quiet ceramic front brake pads for Toyota Corolla with anti-squeal shims and superior stopping power.",
      description: "Engineered specifically for Toyota Corolla (Axio / Fielder / Altis), these premium Brembo ceramic brake pads deliver exceptional stopping power with virtually zero brake dust and silent operation. Formulated with heat-dissipating metallic fibers and ceramic compound for maximum safety under heavy tropical driving conditions.",
      specs: {
        "Position": "Front Axle (Left & Right)",
        "Material": "Premium Low-Metallic Ceramic",
        "Thickness": "17.5 mm",
        "Length": "123 mm",
        "Wear Indicator": "Acoustic Wear Warning Included",
        "Origin": "Japan / Italy OEM Spec",
        "Warranty": "12 Months / 20,000 KM"
      },
      fitment: [
        "Toyota Corolla Axio (NKE165 / NZE161) 2012-2023",
        "Toyota Corolla Fielder (NKE165G / NZE164G) 2012-2023",
        "Toyota Corolla Altis (E170 / E210) 2014-2022",
        "Toyota Premio / Allion (NZT260 / ZRT260) 2007-2021"
      ]
    },
    {
      id: 2,
      name: "BMW 3 Series High-Flow Engine Air Filter",
      slug: "bmw-3-series-air-filter",
      category: "filters",
      categoryName: "Filters",
      brand: "Mann-Filter",
      sku: "FL-BMW-3021",
      partNumber: "13718507320",
      make: "BMW",
      model: "3 Series (F30/G20)",
      yearRange: "2012 - 2022",
      price: 12500,
      oldPrice: 14000,
      discount: 11,
      rating: 4.8,
      reviewsCount: 32,
      inStock: true,
      stockQuantity: 15,
      image: "images/products/air_filter.jpg",
      gallery: [
        "images/products/air_filter.jpg",
        "images/products/oil_filter.jpg"
      ],
      isFeatured: true,
      isBestSeller: true,
      isOnSale: true,
      isNew: true,
      shortDescription: "German OEM standard engine air filter providing 99.8% filtration efficiency for BMW 3-Series engines.",
      description: "Manufactured by Mann-Filter to strict German automotive standards. Features synthetic micro-pleat media that traps microscopic dirt, dust and soot particles while allowing unrestricted high airflow for optimized fuel efficiency and engine throttle response.",
      specs: {
        "Filter Type": "Rigid Engine Air Filter Panel",
        "Media": "Multi-Grade Microfiber Cellulose",
        "Dimensions": "294 mm x 211 mm x 44 mm",
        "Seal Type": "Polyurethane Foam Gasket",
        "Origin": "Germany",
        "Warranty": "6 Months OEM Replacement"
      },
      fitment: [
        "BMW 3 Series 318i / 320i / 320d (F30 / F31) 2012-2019",
        "BMW 3 Series 320i / 330e (G20) 2019-2023",
        "BMW 4 Series Gran Coupe (F36) 2014-2020",
        "BMW 1 Series (F20 / F21) 2012-2019"
      ]
    },
    {
      id: 3,
      name: "Toyota Prius / Aqua Genuine Hybrid Oil Filter",
      slug: "toyota-prius-oil-filter",
      category: "filters",
      categoryName: "Filters",
      brand: "Denso",
      sku: "FL-TY-04152",
      partNumber: "04152-YZZA6",
      make: "Toyota",
      model: "Prius",
      yearRange: "2010 - 2023",
      price: 3200,
      oldPrice: 3800,
      discount: 16,
      rating: 5.0,
      reviewsCount: 76,
      inStock: true,
      stockQuantity: 45,
      image: "images/products/oil_filter.jpg",
      gallery: [
        "images/products/oil_filter.jpg",
        "images/products/air_filter.jpg"
      ],
      isFeatured: true,
      isBestSeller: true,
      isOnSale: true,
      isNew: false,
      shortDescription: "Authentic Denso Japan engine oil filter cartridge with OEM O-rings and drain tube for hybrid engines.",
      description: "Specially developed for Toyota Hybrid Synergy Drive engines (1.5L and 1.8L). Features a precision bypass valve and synthetic blend filtration media that withstands frequent start-stop engine cycles characteristic of hybrid vehicles.",
      specs: {
        "Filter Type": "Cartridge Element with O-Rings",
        "Height": "57 mm",
        "Outer Diameter": "60 mm",
        "Inner Diameter": "28 mm",
        "Origin": "Japan (Denso Corporation)",
        "Warranty": "Guaranteed OEM Fit"
      },
      fitment: [
        "Toyota Prius 1.8L (ZVW30 / ZVW50 / ZVW55) 2009-2023",
        "Toyota Aqua / Prius c 1.5L (NHP10) 2011-2021",
        "Toyota Corolla Axio Hybrid 1.5L (NKE165) 2013-2023",
        "Toyota CH-R 1.8L Hybrid (ZYX10) 2016-2023"
      ]
    },
    {
      id: 4,
      name: "Honda Civic Iridium IX Performance Spark Plug (Set of 4)",
      slug: "honda-civic-spark-plug",
      category: "electrical",
      categoryName: "Electrical",
      brand: "NGK",
      sku: "EL-NGK-7751",
      partNumber: "ILZKR7B11",
      make: "Honda",
      model: "Civic",
      yearRange: "2012 - 2024",
      price: 7400,
      oldPrice: 8500,
      discount: 13,
      rating: 4.9,
      reviewsCount: 29,
      inStock: true,
      stockQuantity: 18,
      image: "images/products/spark_plug.jpg",
      gallery: [
        "images/products/spark_plug.jpg"
      ],
      isFeatured: true,
      isBestSeller: false,
      isOnSale: true,
      isNew: false,
      shortDescription: "Ultra-fine 0.6mm laser welded laser iridium spark plugs for maximum ignition speed, fuel economy, and horsepower.",
      description: "NGK Laser Iridium spark plugs offer superior ignitability and long service life. The smallest tip diameter available (0.6mm) ensures high durability and a consistently stable spark, eliminating misfires even in high-humidity climates.",
      specs: {
        "Center Electrode": "0.6mm Laser Welded Iridium Tip",
        "Ground Electrode": "Platinum Disc Embedded",
        "Thread Size": "12 mm",
        "Thread Reach": "26.5 mm",
        "Hex Size": "16 mm",
        "Pack Quantity": "4 Pieces (Full Engine Set)",
        "Origin": "Japan"
      },
      fitment: [
        "Honda Civic 1.0T / 1.5T / 1.8L (FB / FC / FL) 2012-2024",
        "Honda Vezel / HR-V 1.5L (RU1 / RU3 Hybrid) 2013-2021",
        "Honda Fit / Jazz 1.3L / 1.5L (GK3 / GK5 / GP5 Hybrid) 2013-2020",
        "Honda Grace 1.5L Hybrid (GM4 / GM5) 2014-2020"
      ]
    },
    {
      id: 5,
      name: "Nissan Sunny / Sylphy Ventilated Brake Disc Rotors (Pair)",
      slug: "nissan-sunny-brake-disc",
      category: "brake-system",
      categoryName: "Brake System",
      brand: "Bosch",
      sku: "BD-NS-2089",
      partNumber: "40206-3SG0A",
      make: "Nissan",
      model: "Sunny (N16/N17)",
      yearRange: "2006 - 2021",
      price: 24500,
      oldPrice: 28000,
      discount: 12,
      rating: 4.7,
      reviewsCount: 19,
      inStock: true,
      stockQuantity: 8,
      image: "images/products/brake_disc.jpg",
      gallery: [
        "images/products/brake_disc.jpg",
        "images/products/brake_pad.jpg"
      ],
      isFeatured: true,
      isBestSeller: false,
      isOnSale: true,
      isNew: true,
      shortDescription: "High-carbon anti-corrosion coated front ventilated disc brake rotors for Nissan Sunny and Sylphy.",
      description: "Bosch QuietCast vented disc rotors feature innovative high-carbon technology that maximizes thermal conductivity, resists warping under extreme heat, and prevents brake judder. Treated with an electrostatic zinc coating that resists rust in coastal Sri Lankan weather.",
      specs: {
        "Type": "Ventilated Front Brake Rotors (Pair)",
        "Diameter": "260 mm",
        "Centering Diameter": "68 mm",
        "Number of Holes": "4 Bolt Pattern",
        "Coating": "Anti-Rust Aluminum Zinc Treated",
        "Warranty": "1 Year Replacement Warranty"
      },
      fitment: [
        "Nissan Sunny N16 / N17 (1.3L / 1.5L / 1.6L) 2002-2020",
        "Nissan Sylphy / Bluebird (G11 / B17) 2006-2018",
        "Nissan Tiida Latio (C11 / SC11) 2004-2012",
        "Nissan March / Micra (K12 / K13) 2003-2018"
      ]
    },
    {
      id: 6,
      name: "Suzuki Alto / Wagon R Amaron Go MF Car Battery 12V 35Ah",
      slug: "suzuki-alto-battery",
      category: "electrical",
      categoryName: "Electrical",
      brand: "Amaron",
      sku: "EL-AM-35L",
      partNumber: "AAM-GO-00038B20L",
      make: "Suzuki",
      model: "Alto 800 / K10",
      yearRange: "2010 - 2025",
      price: 28500,
      oldPrice: 32000,
      discount: 11,
      rating: 4.9,
      reviewsCount: 64,
      inStock: true,
      stockQuantity: 12,
      image: "images/products/battery.jpg",
      gallery: [
        "images/products/battery.jpg"
      ],
      isFeatured: true,
      isBestSeller: true,
      isOnSale: true,
      isNew: false,
      shortDescription: "Zero-maintenance automotive battery with patented Silven X alloy technology for extreme tropical weather durability.",
      description: "Amaron GO 38B20L is the ultimate maintenance-free battery for Suzuki Alto, Wagon R, and small passenger cars. Built with patented Silven X alloy grid technology that resists high under-hood temperatures and ensures quick cranking even after prolonged parking.",
      specs: {
        "Voltage": "12 Volts",
        "Capacity (AH)": "35 Ah",
        "Cold Cranking Amps (CCA)": "300 A",
        "Terminal Type": "Small Post (Left Hand - JIS)",
        "Technology": "Silver-Alloy Maintenance Free",
        "Warranty": "24 Months Official Islandwide Warranty"
      },
      fitment: [
        "Suzuki Alto 800 / K10 / Japanese Alto (HA25 / HA36) 2008-2025",
        "Suzuki Wagon R (MH23 / MH34 / MH44 / MH55) 2010-2023",
        "Suzuki Spacia / Hustler (MK32 / MK42 / MR31) 2013-2022",
        "Daihatsu Mira / Move / Tanto 2012-2023"
      ]
    },
    {
      id: 7,
      name: "Toyota Hilux / Fortuner Heavy-Duty Aluminum Radiator",
      slug: "toyota-hilux-radiator",
      category: "cooling-system",
      categoryName: "Cooling System",
      brand: "KoyoRad",
      sku: "CL-TY-HL405",
      partNumber: "16400-0L140",
      make: "Toyota",
      model: "Hilux",
      yearRange: "2005 - 2021",
      price: 42000,
      oldPrice: 48000,
      discount: 12,
      rating: 4.8,
      reviewsCount: 22,
      inStock: true,
      stockQuantity: 6,
      image: "images/products/radiator.jpg",
      gallery: [
        "images/products/radiator.jpg"
      ],
      isFeatured: true,
      isBestSeller: false,
      isOnSale: true,
      isNew: false,
      shortDescription: "Direct OEM replacement brazed aluminum core radiator with reinforced thermal plastic tanks for Toyota Hilux D4D.",
      description: "Built to withstand intense commercial usage and off-road stress. High efficiency micro-fin aluminum cooling core delivers 25% higher heat rejection compared to standard units. Includes integrated oil cooler for automatic transmission models.",
      specs: {
        "Core Material": "Brazed Aluminum Tube & Fin",
        "Tank Material": "High-Density Reinforced PA66 Polymer",
        "Core Thickness": "26 mm",
        "Core Size": "525 mm x 650 mm",
        "Transmission Cooler": "Integrated Automatic Cooler Included",
        "Origin": "Japan / Thailand Koyo OEM"
      },
      fitment: [
        "Toyota Hilux Vigo / Revo 2.5L / 3.0L D4D (KUN25 / KUN26 / GUN125) 2005-2022",
        "Toyota Fortuner 2.5L / 3.0L Diesel (KUN51 / KUN60) 2005-2021",
        "Toyota HiAce Commuter 2KD-FTV 2.5L (KDH200) 2005-2018"
      ]
    },
    {
      id: 8,
      name: "BMW 3 / 5 Series Hydraulic Engine Mount Bushing",
      slug: "bmw-engine-mount",
      category: "engine-parts",
      categoryName: "Engine Parts",
      brand: "Lemforder",
      sku: "EN-BMW-EM99",
      partNumber: "22116855456",
      make: "BMW",
      model: "3 Series (F30/G20)",
      yearRange: "2012 - 2020",
      price: 18900,
      oldPrice: 21500,
      discount: 12,
      rating: 4.9,
      reviewsCount: 17,
      inStock: true,
      stockQuantity: 10,
      image: "images/products/stabilizer_link.jpg",
      gallery: [
        "images/products/stabilizer_link.jpg"
      ],
      isFeatured: true,
      isBestSeller: false,
      isOnSale: false,
      isNew: true,
      shortDescription: "German Lemforder hydro-mount damper that absorbs engine vibrations for maximum cabin silence.",
      description: "OEM hydraulic rubber-metal damping engine mount for BMW TwinPower Turbo engines. Features a sealed hydraulic chamber that absorbs high and low-frequency vibrations, restoring the signature smooth BMW driving experience.",
      specs: {
        "Mounting Type": "Hydro-Mount (Fluid Filled Damper)",
        "Fitting Position": "Right Front / Left Front Axle",
        "Material": "Natural Rubber Compound & Forged Alloy",
        "Origin": "Germany (ZF Lemforder)",
        "Warranty": "1 Year"
      },
      fitment: [
        "BMW 3 Series (F30 / F31 / F34) 318i / 320i / 328i 2012-2019",
        "BMW 4 Series (F32 / F33 / F36) 420i / 428i 2013-2020",
        "BMW 5 Series (F10 / G30) 520i / 520d 2011-2020"
      ]
    },
    {
      id: 9,
      name: "Toyota Land Cruiser Prado Gas-Charged Heavy-Duty Shock Absorber",
      slug: "toyota-prado-shock-absorber",
      category: "body-parts",
      categoryName: "Body Parts",
      brand: "KYB",
      sku: "SH-TY-PR34",
      partNumber: "341344",
      make: "Toyota",
      model: "Land Cruiser Prado",
      yearRange: "2003 - 2022",
      price: 34000,
      oldPrice: 38500,
      discount: 11,
      rating: 5.0,
      reviewsCount: 38,
      inStock: true,
      stockQuantity: 14,
      image: "images/products/shock_absorber.jpg",
      gallery: [
        "images/products/shock_absorber.jpg"
      ],
      isFeatured: true,
      isBestSeller: true,
      isOnSale: true,
      isNew: false,
      shortDescription: "KYB Excel-G twin-tube nitrogen gas pressurized shock absorber for Land Cruiser Prado 120 / 150 series.",
      description: "Restores original handling, ride stability, and tire traction for Toyota Prado SUVs. Patented valving plus pressurized nitrogen gas account for specifically engineered comfort and control over rough Sri Lankan terrains.",
      specs: {
        "Design": "Twin-Tube Nitrogen Gas Pressurized",
        "Position": "Front / Rear Suspension Strut",
        "Piston Rod": "Hard Chromed Sintered Iron",
        "Origin": "Japan (KYB Corporation)",
        "Warranty": "18 Months / 30,000 KM"
      },
      fitment: [
        "Toyota Land Cruiser Prado (GRJ120 / KDJ120 / TRJ120) 2002-2009",
        "Toyota Land Cruiser Prado (GDJ150 / TRJ150 / GRJ150) 2009-2023",
        "Toyota Hilux Surf (RZN215 / KDN215) 2002-2009"
      ]
    },
    {
      id: 10,
      name: "Ultra-Bright 120W Turbo LED Headlight Bulb Set H4 / H11 6000K",
      slug: "ultra-bright-led-headlight-set",
      category: "electrical",
      categoryName: "Electrical",
      brand: "Philips",
      sku: "EL-LED-H4-99",
      partNumber: "HL-CSP-12000LM",
      make: "Toyota",
      model: "Corolla",
      yearRange: "2000 - 2026",
      price: 9500,
      oldPrice: 12000,
      discount: 21,
      rating: 4.8,
      reviewsCount: 88,
      inStock: true,
      stockQuantity: 30,
      image: "images/products/led_headlight.jpg",
      gallery: [
        "images/products/led_headlight.jpg"
      ],
      isFeatured: true,
      isBestSeller: true,
      isOnSale: true,
      isNew: true,
      shortDescription: "12,000 Lumens diamond white 6000K automotive LED conversion kit with high-speed cooling fan and CANBUS decoder.",
      description: "Plug-and-play automotive LED bulbs delivering 300% more road visibility compared to standard halogen bulbs. Features a sharp cutoff line to prevent blinding oncoming traffic, aviation-grade 6063 aluminum heat sink and IP68 waterproof rating.",
      specs: {
        "Socket Type": "H4 (Hi/Lo Beam) / H11 / HB3",
        "Luminous Flux": "12,000 LM per pair (6000 LM/bulb)",
        "Color Temperature": "6000K Crisp Cool White",
        "Operating Voltage": "DC 9V - 32V",
        "Cooling": "12,000 RPM Silent Turbo Fan",
        "Lifespan": "50,000+ Hours"
      },
      fitment: [
        "Universal fitment for all Toyota, Nissan, Honda, Suzuki, Mitsubishi vehicles using H4/H11 bulb sockets"
      ]
    },
    {
      id: 11,
      name: "Honda Fit / Vezel Front Stabilizer Link Rod Set (Pair)",
      slug: "honda-fit-stabilizer-link",
      category: "engine-parts",
      categoryName: "Engine Parts",
      brand: "555 Sankei",
      sku: "SB-HD-555-FL",
      partNumber: "51320-T5A-003",
      make: "Honda",
      model: "Fit / Jazz",
      yearRange: "2014 - 2022",
      price: 6800,
      oldPrice: 7900,
      discount: 14,
      rating: 4.7,
      reviewsCount: 15,
      inStock: true,
      stockQuantity: 16,
      image: "images/products/stabilizer_link.jpg",
      gallery: [
        "images/products/stabilizer_link.jpg"
      ],
      isFeatured: false,
      isBestSeller: false,
      isOnSale: true,
      isNew: false,
      shortDescription: "Japanese 555 heavy-duty sway bar stabilizer link rods to eliminate suspension clunks and restore cornering balance.",
      description: "Precision engineered ball joints with high-grade synthetic grease and chloroprene rubber dust boots. Resists grit, water ingress, and heavy suspension jolts over uneven roads.",
      specs: {
        "Position": "Front Stabilizer Sway Bar (Left & Right)",
        "Thread Size": "M10 x 1.25",
        "Length": "285 mm",
        "Origin": "Japan (Three Five Sankei Co.)",
        "Warranty": "1 Year"
      },
      fitment: [
        "Honda Fit / Jazz (GK3 / GK5 / GP5 Hybrid) 2013-2020",
        "Honda Vezel / HR-V (RU1 / RU3 / RU4) 2013-2021",
        "Honda Shuttle (GP7 / GP8) 2015-2022",
        "Honda Grace (GM4 / GM5) 2014-2020"
      ]
    },
    {
      id: 12,
      name: "Toyota Aqua / Vitz Rear Brake Shoe Set",
      slug: "toyota-aqua-rear-brake-pad",
      category: "brake-system",
      categoryName: "Brake System",
      brand: "Akebono",
      sku: "BP-TY-AQ98",
      partNumber: "04495-52160",
      make: "Toyota",
      model: "Aqua",
      yearRange: "2011 - 2021",
      price: 7900,
      oldPrice: 8900,
      discount: 11,
      rating: 4.9,
      reviewsCount: 41,
      inStock: true,
      stockQuantity: 20,
      image: "images/products/rear_brake_pad.jpg",
      gallery: [
        "images/products/rear_brake_pad.jpg",
        "images/products/brake_pad.jpg"
      ],
      isFeatured: false,
      isBestSeller: true,
      isOnSale: false,
      isNew: false,
      shortDescription: "Akebono Japan rear drum brake shoes designed for quiet, progressive braking and extended lining life.",
      description: "Akebono brake shoes are the number one OEM supplier for Japanese auto manufacturers. Delivers consistent brake pedal firmness, reduced brake fade on downhill gradients, and minimal drum wear.",
      specs: {
        "Position": "Rear Drum Axle Set (4 pieces)",
        "Material": "Non-Asbestos Organic OEM Formula",
        "Drum Diameter": "200 mm",
        "Origin": "Japan (Akebono Brake Industry)",
        "Warranty": "12 Months"
      },
      fitment: [
        "Toyota Aqua (NHP10) 2011-2021",
        "Toyota Vitz / Yaris (KSP130 / NSP130 / NHP130) 2011-2020",
        "Toyota Corolla Axio (NKE165 / NZE161) 2012-2022",
        "Toyota Passo (KGC30 / NGC30) 2010-2016"
      ]
    },
    {
      id: 13,
      name: "Honda Civic / Vezel Multi-Layer Cabin AC Air Filter",
      slug: "honda-air-filter-cabin",
      category: "filters",
      categoryName: "Filters",
      brand: "Denso",
      sku: "FL-HD-AC33",
      partNumber: "80292-TG0-Q01",
      make: "Honda",
      model: "Civic",
      yearRange: "2012 - 2023",
      price: 3900,
      oldPrice: 4500,
      discount: 13,
      rating: 4.8,
      reviewsCount: 36,
      inStock: true,
      stockQuantity: 28,
      image: "images/products/honda_air_filter.jpg",
      gallery: [
        "images/products/honda_air_filter.jpg"
      ],
      isFeatured: false,
      isBestSeller: false,
      isOnSale: true,
      isNew: false,
      shortDescription: "Activated carbon cabin air filter that removes 99% of dust, diesel fumes, pollen, and odor inside the car cabin.",
      description: "Keep your vehicle interior fresh and allergy-free. Denso activated carbon cabin air filters capture PM2.5 particulate matter, exhaust soot, and airborne allergens while neutralizing foul odors.",
      specs: {
        "Filter Medium": "Electrostatic Layer + Activated Charcoal",
        "Dimensions": "211 mm x 205 mm x 30 mm",
        "Origin": "Japan OEM Spec",
        "Service Interval": "Replace every 15,000 KM"
      },
      fitment: [
        "Honda Civic (FB / FC / FK / FL) 2012-2023",
        "Honda Vezel / HR-V 2013-2022",
        "Honda Fit / Jazz (GE / GK / GR) 2008-2023",
        "Honda CR-V (RM / RW) 2012-2022"
      ]
    }
  ],

  // Customer Reviews
  reviews: [
    {
      id: 1,
      author: "Kasun Jayasuriya",
      location: "Colombo 07, Sri Lanka",
      vehicle: "Toyota Corolla Axio 2018",
      rating: 5,
      date: "2 days ago",
      comment: "Found the exact front brake pads for my Axio in 2 minutes using their vehicle selector. Delivery to Colombo was next morning. Authentic Brembo parts with warranty seal!"
    },
    {
      id: 2,
      author: "Dinesh Fernando",
      location: "Kandy, Sri Lanka",
      vehicle: "Honda Vezel 2017",
      rating: 5,
      date: "1 week ago",
      comment: "Best prices in Sri Lanka for genuine NGK Iridium spark plugs and Denso filters. Customer service called to confirm my chassis code fitment before sending. Highly recommended!"
    },
    {
      id: 3,
      author: "Mohamed Rilwan",
      location: "Galle, Sri Lanka",
      vehicle: "Suzuki Alto 800",
      rating: 5,
      date: "2 weeks ago",
      comment: "Ordered Amaron battery on Friday, arrived safely packed in Galle on Saturday. Installed easily and starts up instantly. AUTO HUB is my go-to auto parts store."
    }
  ],

  // Popular Brands
  brands: [
    { name: "Denso", country: "Japan", logoText: "DENSO" },
    { name: "Brembo", country: "Italy", logoText: "brembo" },
    { name: "Bosch", country: "Germany", logoText: "BOSCH" },
    { name: "NGK", country: "Japan", logoText: "NGK SPARK PLUGS" },
    { name: "KYB", country: "Japan", logoText: "KYB SHOCKS" },
    { name: "Amaron", country: "India / US", logoText: "AMARON" },
    { name: "Mann-Filter", country: "Germany", logoText: "MANN FILTER" },
    { name: "555 Sankei", country: "Japan", logoText: "555 SANKEI" }
  ],

  // Shipping Rates for Sri Lankan Provinces
  shippingRates: {
    "Western Province (Colombo, Gampaha, Kalutara)": { standard: 350, express: 600 },
    "Central Province (Kandy, Matale, Nuwara Eliya)": { standard: 450, express: 750 },
    "Southern Province (Galle, Matara, Hambantota)": { standard: 450, express: 750 },
    "North Western Province (Kurunegala, Puttalam)": { standard: 450, express: 750 },
    "Sabaragamuwa Province (Ratnapura, Kegalle)": { standard: 450, express: 750 },
    "Eastern Province (Trincomalee, Batticaloa, Ampara)": { standard: 550, express: 900 },
    "Northern Province (Jaffna, Kilinochchi, Vavuniya)": { standard: 600, express: 950 },
    "North Central Province (Anuradhapura, Polonnaruwa)": { standard: 500, express: 850 },
    "Uva Province (Badulla, Monaragala)": { standard: 500, express: 850 }
  }
};

// Export to window
if (typeof window !== 'undefined') {
  window.AUTO_HUB_DATA = AUTO_HUB_DATA;
}
