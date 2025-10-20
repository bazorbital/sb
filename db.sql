CREATE TABLE Customers (
  customer_id       INT AUTO_INCREMENT PRIMARY KEY,
  name              VARCHAR(150)         NOT NULL,
  email             VARCHAR(190)         UNIQUE,
  phone             VARCHAR(50),
  category          VARCHAR(50),                         -- tetszőleges címke (VIP, stb.)
  contact_json      JSON,                                -- extra elérhetőségek/adatok
  is_deleted        BOOLEAN               NOT NULL DEFAULT FALSE,
  created_at        DATETIME              NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME              NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
//Mi micsoda: alap ügyféladatok; contact_json rugalmas mezőknek (pl. számlázási cím).
CREATE TABLE Employees (
  employee_id       INT AUTO_INCREMENT PRIMARY KEY,
  name              VARCHAR(150)         NOT NULL,
  email             VARCHAR(190)         UNIQUE,
  phone             VARCHAR(50),
  specialization    VARCHAR(120),                      -- pl. orvos szakterület
  available_online  BOOLEAN              NOT NULL DEFAULT FALSE,
  is_deleted        BOOLEAN              NOT NULL DEFAULT FALSE,
  created_at        DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE Locations (
  location_id       INT AUTO_INCREMENT PRIMARY KEY,
  name              VARCHAR(150)         NOT NULL,
  address           VARCHAR(255),
  is_event_location BOOLEAN              NOT NULL DEFAULT TRUE,
  is_deleted        BOOLEAN              NOT NULL DEFAULT FALSE,
  created_at        DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE EmployeeLocations (
  employee_id       INT NOT NULL,
  location_id       INT NOT NULL,
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (employee_id, location_id),
  FOREIGN KEY (employee_id) REFERENCES Employees(employee_id),
  FOREIGN KEY (location_id) REFERENCES Locations(location_id)
);
CREATE TABLE OpeningHours (
  opening_hour_id   INT AUTO_INCREMENT PRIMARY KEY,
  location_id       INT NOT NULL,
  day_of_week       TINYINT NOT NULL,                  -- 0=vasárnap ... 6=szombat (választható konvenció)
  open_time         TIME,
  close_time        TIME,
  is_closed         BOOLEAN NOT NULL DEFAULT FALSE,
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  FOREIGN KEY (location_id) REFERENCES Locations(location_id)
);
CREATE TABLE EmployeeWorkingHours (
  working_hour_id   INT AUTO_INCREMENT PRIMARY KEY,
  employee_id       INT NOT NULL,
  day_of_week       TINYINT NOT NULL,
  start_time        TIME,
  end_time          TIME,
  is_off_day        BOOLEAN NOT NULL DEFAULT FALSE,
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  FOREIGN KEY (employee_id) REFERENCES Employees(employee_id)
);
CREATE TABLE SpecialHours (
  special_hour_id   INT AUTO_INCREMENT PRIMARY KEY,
  entity_type       ENUM('location','employee') NOT NULL,
  entity_id         INT NOT NULL,
  date              DATE NOT NULL,
  open_time         TIME,
  close_time        TIME,
  is_closed         BOOLEAN NOT NULL DEFAULT FALSE,
  note              VARCHAR(255),
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  INDEX (entity_type, entity_id, date)
);
CREATE TABLE Offerings (
  offering_id       INT AUTO_INCREMENT PRIMARY KEY,
  type              ENUM('service','bundle') NOT NULL,
  name              VARCHAR(150) NOT NULL,
  description       TEXT,
  duration_minutes  INT,                                 -- szolgáltatásra jellemző (bundle-nál NULL)
  buffer_before_min INT NOT NULL DEFAULT 0,
  buffer_after_min  INT NOT NULL DEFAULT 0,
  is_collaborative  BOOLEAN NOT NULL DEFAULT FALSE,      -- csoportos/együttműködő jelleg
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE BundleItems (
  bundle_id         INT NOT NULL,
  item_offering_id  INT NOT NULL,
  quantity          DECIMAL(10,2) NOT NULL DEFAULT 1,    -- ha számít
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (bundle_id, item_offering_id),
  FOREIGN KEY (bundle_id)        REFERENCES Offerings(offering_id),
  FOREIGN KEY (item_offering_id) REFERENCES Offerings(offering_id)
);
CREATE TABLE Taxes (
  tax_id            INT AUTO_INCREMENT PRIMARY KEY,
  name              VARCHAR(100) NOT NULL,               -- pl. ÁFA 27%
  rate_percent      DECIMAL(5,2) NOT NULL,               -- 27.00
  description       VARCHAR(255),
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE
);
CREATE TABLE Prices (
  price_id          INT AUTO_INCREMENT PRIMARY KEY,
  offering_id       INT NOT NULL,
  currency          CHAR(3) NOT NULL DEFAULT 'HUF',
  base_price        DECIMAL(12,2) NOT NULL,
  tax_id            INT,                                 -- NULL = adómentes
  discount_price    DECIMAL(12,2),
  discount_start    DATETIME,
  discount_end      DATETIME,
  valid_from        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  valid_to          DATETIME,                             -- NULL = visszavonásig
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  FOREIGN KEY (offering_id) REFERENCES Offerings(offering_id),
  FOREIGN KEY (tax_id)      REFERENCES Taxes(tax_id),
  INDEX (offering_id, valid_from, valid_to)
);
CREATE TABLE Bookings (
  booking_id        INT AUTO_INCREMENT PRIMARY KEY,
  booking_type      ENUM('appointment','event','rental','room') NOT NULL,
  offering_id       INT,                                   -- lehet NULL (pl. tisztán terem bérlés)
  customer_id       INT,                                   -- eventnél lehet NULL, ha belső szervezés
  employee_id       INT,                                   -- akihez tartozik / szervező
  location_id       INT,
  start_time        DATETIME NOT NULL,
  end_time          DATETIME NOT NULL,
  status            ENUM('pending','confirmed','canceled','completed') NOT NULL DEFAULT 'pending',
  is_online         BOOLEAN NOT NULL DEFAULT FALSE,        -- online konzultáció jelző
  capacity_min      INT,
  capacity_max      INT,
  notes             TEXT,
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  FOREIGN KEY (offering_id) REFERENCES Offerings(offering_id),
  FOREIGN KEY (customer_id) REFERENCES Customers(customer_id),
  FOREIGN KEY (employee_id) REFERENCES Employees(employee_id),
  FOREIGN KEY (location_id) REFERENCES Locations(location_id),
  INDEX (start_time), INDEX (employee_id), INDEX (location_id)
);
CREATE TABLE BookingParticipants (
  booking_id        INT NOT NULL,
  customer_id       INT NOT NULL,
  participation_status ENUM('invited','confirmed','canceled','attended') NOT NULL DEFAULT 'confirmed',
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (booking_id, customer_id),
  FOREIGN KEY (booking_id)  REFERENCES Bookings(booking_id),
  FOREIGN KEY (customer_id) REFERENCES Customers(customer_id)
);
CREATE TABLE OnlineDetails (
  booking_id        INT PRIMARY KEY,
  platform          ENUM('Zoom','Teams','Meet','Other') NOT NULL,
  meeting_link      VARCHAR(500) NOT NULL,
  access_code       VARCHAR(100),
  recording_url     VARCHAR(500),
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  FOREIGN KEY (booking_id) REFERENCES Bookings(booking_id)
);
CREATE TABLE Resources (
  resource_id       INT AUTO_INCREMENT PRIMARY KEY,
  type              ENUM('equipment','room','other') NOT NULL,
  name              VARCHAR(150) NOT NULL,
  location_id       INT,
  capacity          INT,
  status            ENUM('available','maintenance','retired') NOT NULL DEFAULT 'available',
  is_event_resource BOOLEAN NOT NULL DEFAULT TRUE,
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  FOREIGN KEY (location_id) REFERENCES Locations(location_id)
);
CREATE TABLE ResourceBookings (
  resource_booking_id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id        INT NOT NULL,
  resource_id       INT NOT NULL,
  start_time        DATETIME NOT NULL,
  end_time          DATETIME NOT NULL,
  status            ENUM('reserved','canceled') NOT NULL DEFAULT 'reserved',
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  FOREIGN KEY (booking_id)  REFERENCES Bookings(booking_id),
  FOREIGN KEY (resource_id) REFERENCES Resources(resource_id),
  INDEX (resource_id, start_time, end_time)
);
CREATE TABLE Patients (
  patient_id        INT AUTO_INCREMENT PRIMARY KEY,
  customer_id       INT UNIQUE,                         -- ugyanaz a személy mint ügyfél
  medical_notes     TEXT,
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  FOREIGN KEY (customer_id) REFERENCES Customers(customer_id)
);
CREATE TABLE Coupons (
  coupon_id         INT AUTO_INCREMENT PRIMARY KEY,
  code              VARCHAR(50) NOT NULL UNIQUE,
  type              ENUM('percentage','fixed') NOT NULL,
  value             DECIMAL(12,2) NOT NULL,
  max_discount      DECIMAL(12,2),
  valid_from        DATETIME NOT NULL,
  valid_to          DATETIME NOT NULL,
  usage_limit       INT,
  usage_count       INT NOT NULL DEFAULT 0,
  status            ENUM('active','expired','disabled') NOT NULL DEFAULT 'active',
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE CouponTargets (
  coupon_target_id  INT AUTO_INCREMENT PRIMARY KEY,
  coupon_id         INT NOT NULL,
  target_scope      ENUM('all','offering') NOT NULL,     -- nincs külön service/package/collab
  offering_id       INT,                                  -- csak ha target_scope='offering'
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  FOREIGN KEY (coupon_id)   REFERENCES Coupons(coupon_id),
  FOREIGN KEY (offering_id) REFERENCES Offerings(offering_id)
);
CREATE TABLE CouponUsages (
  coupon_usage_id   INT AUTO_INCREMENT PRIMARY KEY,
  coupon_id         INT NOT NULL,
  booking_id        INT,                                 -- melyik foglalásnál
  amount            DECIMAL(12,2) NOT NULL,              -- engedmény összege
  used_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  FOREIGN KEY (coupon_id) REFERENCES Coupons(coupon_id),
  FOREIGN KEY (booking_id) REFERENCES Bookings(booking_id)
);
CREATE TABLE GiftCardTypes (
  gift_card_type_id INT AUTO_INCREMENT PRIMARY KEY,
  name              VARCHAR(120) NOT NULL,
  description       TEXT,
  default_value     DECIMAL(12,2) NOT NULL,
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE
);
CREATE TABLE GiftCards (
  gift_card_id      INT AUTO_INCREMENT PRIMARY KEY,
  gift_card_type_id INT NOT NULL,
  code              VARCHAR(60) NOT NULL UNIQUE,
  purchase_date     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  initial_value     DECIMAL(12,2) NOT NULL,
  current_value     DECIMAL(12,2) NOT NULL,
  status            ENUM('active','used','expired','blocked') NOT NULL DEFAULT 'active',
  purchaser_customer_id INT,                              -- ki vette
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  FOREIGN KEY (gift_card_type_id)     REFERENCES GiftCardTypes(gift_card_type_id),
  FOREIGN KEY (purchaser_customer_id) REFERENCES Customers(customer_id)
);
CREATE TABLE GiftCardTransactions (
  transaction_id    INT AUTO_INCREMENT PRIMARY KEY,
  gift_card_id      INT NOT NULL,
  booking_id        INT,                                  -- melyik foglaláshoz kapcsolódik
  amount            DECIMAL(12,2) NOT NULL,               -- negatív = terhelés, pozitív = feltöltés/refund
  balance_after     DECIMAL(12,2) NOT NULL,
  occurred_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  FOREIGN KEY (gift_card_id) REFERENCES GiftCards(gift_card_id),
  FOREIGN KEY (booking_id)   REFERENCES Bookings(booking_id)
);
CREATE TABLE Payments (
  payment_id        INT AUTO_INCREMENT PRIMARY KEY,
  booking_id        INT,                                   -- ajándékkártya vásárlásnál NULL is lehet
  payment_method    ENUM('cash','card','transfer','gift_card','other') NOT NULL,
  amount            DECIMAL(12,2) NOT NULL,
  currency          CHAR(3) NOT NULL DEFAULT 'HUF',
  gift_card_id      INT,                                   -- ha gift_card
  coupon_id         INT,                                   -- ha kedvezmény kuponnal (nyilvántartási cél)
  payment_date      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  FOREIGN KEY (booking_id)   REFERENCES Bookings(booking_id),
  FOREIGN KEY (gift_card_id) REFERENCES GiftCards(gift_card_id),
  FOREIGN KEY (coupon_id)    REFERENCES Coupons(coupon_id),
  INDEX (payment_date)
);

CREATE VIEW DailyRevenue AS
SELECT
  DATE(payment_date) AS revenue_date,
  currency,
  SUM(amount)        AS total_revenue,
  COUNT(*)           AS payment_count
FROM Payments
WHERE is_deleted = FALSE
GROUP BY DATE(payment_date), currency;

CREATE TABLE NotificationTemplates (
  template_id       INT AUTO_INCREMENT PRIMARY KEY,
  event_type        ENUM('BookingCreated','BookingCanceled','BookingReminder','BookingUpdated') NOT NULL,
  recipient_type    ENUM('customer','employee') NOT NULL,
  channel           ENUM('email','sms','push')  NOT NULL,
  subject           VARCHAR(200),
  body              TEXT NOT NULL,                          -- személyre szabható placeholderekkel
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  UNIQUE KEY u_tpl (event_type, recipient_type, channel)
);

CREATE TABLE NotificationSettings (
  setting_id        INT AUTO_INCREMENT PRIMARY KEY,
  user_type         ENUM('customer','employee') NOT NULL,
  user_id           INT NOT NULL,
  enable_email      BOOLEAN NOT NULL DEFAULT TRUE,
  enable_sms        BOOLEAN NOT NULL DEFAULT FALSE,
  enable_push       BOOLEAN NOT NULL DEFAULT FALSE,
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  UNIQUE KEY u_user (user_type, user_id)
);

CREATE TABLE NotificationQueue (
  notification_id   INT AUTO_INCREMENT PRIMARY KEY,
  booking_id        INT,                                     -- ha foglaláshoz kötött
  recipient_type    ENUM('customer','employee') NOT NULL,
  recipient_id      INT NOT NULL,
  channel           ENUM('email','sms','push')  NOT NULL,
  subject           VARCHAR(200),
  body              TEXT NOT NULL,
  scheduled_at      DATETIME NOT NULL,                       -- mikor küldjük
  sent_at           DATETIME,
  status            ENUM('pending','sent','failed','canceled') NOT NULL DEFAULT 'pending',
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  FOREIGN KEY (booking_id) REFERENCES Bookings(booking_id),
  INDEX (status, scheduled_at)
);
CREATE TABLE EventLog (
  event_id          INT AUTO_INCREMENT PRIMARY KEY,
  event_type        ENUM('BookingCreated','BookingCanceled','BookingReminder','BookingUpdated') NOT NULL,
  booking_id        INT,
  occurred_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  payload_json      JSON,
  is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
  FOREIGN KEY (booking_id) REFERENCES Bookings(booking_id),
  INDEX (event_type, occurred_at)
);

