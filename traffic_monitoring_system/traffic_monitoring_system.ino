#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClient.h>
#include <LiquidCrystal_I2C.h>

// ====== WiFi Configuration ======
const char* ssid = "REDEEMER";
const char* password = "RENE12345";
const char* serverURL = "http://10.103.50.145/traffic_monitoring_project/api/receive_data.php";

// ====== PIN CONFIGURATION ======
const int trigPin1 = D6;  // North sensor
const int echoPin1 = D7;
const int trigPin2 = D5;  // South sensor
const int echoPin2 = D8;

// LED Indicators
const int ledPin1 = D0;
const int ledPin2 = D4;

// ====== LCD SETUP ======
LiquidCrystal_I2C lcd(0x27, 16, 2);

// ====== CALIBRATION ======
int roadHeight1 = 0;
int roadHeight2 = 0;
const int CALIBRATION_SAMPLES = 5;
bool wifiConnected = false;

// ====== SETUP ======
void setup() {
  Serial.begin(115200);
  lcd.begin();
  lcd.backlight();
  lcd.clear();
  lcd.print("System Boot...");
  delay(2000);

  pinMode(trigPin1, OUTPUT);
  pinMode(echoPin1, INPUT);
  pinMode(trigPin2, OUTPUT);
  pinMode(echoPin2, INPUT);
  pinMode(ledPin1, OUTPUT);
  pinMode(ledPin2, OUTPUT);

  digitalWrite(ledPin1, LOW);
  digitalWrite(ledPin2, LOW);

  connectToWiFi();  // Connect to WiFi first
  calibrateRoad();

  lcd.clear();
  lcd.print("Ready");
  lcd.setCursor(0, 1);
  lcd.print("Monitoring...");

  Serial.println("=== Road Height Test ===");
  Serial.print("Road1: "); Serial.print(roadHeight1); Serial.println(" cm");
  Serial.print("Road2: "); Serial.print(roadHeight2); Serial.println(" cm");
  Serial.println("Waiting for vehicles...");
}

// ====== MAIN LOOP ======
void loop() {
  checkSensor("North", trigPin1, echoPin1, roadHeight1, ledPin1);
  checkSensor("South", trigPin2, echoPin2, roadHeight2, ledPin2);
  delay(200);
}

// ====== CALIBRATION FUNCTION ======
void calibrateRoad() {
  lcd.clear();
  lcd.print("Calibrating...");
  lcd.setCursor(0, 1);
  lcd.print("Clear Road");
  delay(3000);

  int sum1 = 0, sum2 = 0;
  for (int i = 0; i < CALIBRATION_SAMPLES; i++) {
    int d1 = getDistance(trigPin1, echoPin1);
    int d2 = getDistance(trigPin2, echoPin2);
    if (d1 > 0) sum1 += d1;
    if (d2 > 0) sum2 += d2;
    delay(200);
  }

  roadHeight1 = sum1 / CALIBRATION_SAMPLES;
  roadHeight2 = sum2 / CALIBRATION_SAMPLES;

  lcd.clear();
  lcd.print("Calibration OK");
  lcd.setCursor(0, 1);
  lcd.print("R1:" + String(roadHeight1) + " R2:" + String(roadHeight2));
  delay(2000);
}

// ====== SENSOR CHECK FUNCTION ======
void checkSensor(String dir, int trigPin, int echoPin, int roadHeight, int ledPin) {
  int distance = getDistance(trigPin, echoPin);
  if (distance <= 0) return;

  int heightDiff = roadHeight - distance;

  Serial.print(dir);
  Serial.print(" distance: "); Serial.print(distance);
  Serial.print("cm, road: "); Serial.print(roadHeight);
  Serial.print("cm, diff: "); Serial.println(heightDiff);

  if (heightDiff > 3) { // Detection threshold
    float vehicleHeight = (heightDiff * 0.05) + 0.5;
    String vehicleType = classifyVehicle(vehicleHeight, distance);

    digitalWrite(ledPin, HIGH);

    lcd.clear();
    lcd.print(dir + ": " + vehicleType);
    lcd.setCursor(0, 1);
    lcd.print("H:" + String(vehicleHeight, 1) + "m D:" + String(distance) + "cm");

    Serial.print(dir);
    Serial.print(" - Height Diff: "); Serial.print(heightDiff);
    Serial.print("cm, Vehicle Height: "); Serial.print(vehicleHeight, 1);
    Serial.print("m, Type: "); Serial.println(vehicleType);

    // 🧾 Send data to database
    if (wifiConnected && vehicleType != "Unknown") {
      sendData(dir, vehicleType, vehicleHeight, distance);
    }

    delay(1500);
    digitalWrite(ledPin, LOW);

    lcd.clear();
    lcd.print("Ready");
    lcd.setCursor(0, 1);
    lcd.print("Monitoring...");
  }
}

// ====== VEHICLE CLASSIFICATION ======
String classifyVehicle(float height, int distance) {
  if ((height >= 0.65 && height <= 0.75) && (distance >= 5 && distance <= 7)) {
    return "Car";
  } else if ((height > 0.75 && height <= 0.85) && (distance >= 3 && distance <= 5)) {
    return "Bus";
  } else if ((height > 0.85 && height <= 0.95) && (distance >= 2 && distance <= 4)) {
    return "Truck";
  } else {
    return "Unknown";
  }
}

// ====== ULTRASONIC DISTANCE ======
int getDistance(int trigPin, int echoPin) {
  digitalWrite(trigPin, LOW);
  delayMicroseconds(2);
  digitalWrite(trigPin, HIGH);
  delayMicroseconds(10);
  digitalWrite(trigPin, LOW);

  long duration = pulseIn(echoPin, HIGH, 30000);
  if (duration == 0) return -1;

  int distance = duration * 0.034 / 2;
  if (distance <= 0 || distance > 400) return -1;

  return distance;
}

// ====== WIFI CONNECT ======
void connectToWiFi() {
  Serial.print("Connecting to WiFi ");
  Serial.println(ssid);
  WiFi.begin(ssid, password);

  int attempt = 0;
  while (WiFi.status() != WL_CONNECTED && attempt < 20) {
    delay(1000);
    Serial.print(".");
    attempt++;
  }
  if (WiFi.status() == WL_CONNECTED) {
    wifiConnected = true;
    Serial.println("\nWiFi Connected!");
    Serial.print("IP: "); Serial.println(WiFi.localIP());
    lcd.clear();
    lcd.print("WiFi Connected");
    delay(1000);
  } else {
    wifiConnected = false;
    Serial.println("\nWiFi Failed!");
    lcd.clear();
    lcd.print("WiFi Failed");
    delay(1500);
  }
}

// ====== SEND DATA FUNCTION ======
void sendData(String direction, String type, float height, int distance) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi Disconnected!");
    return;
  }

  HTTPClient http;
  WiFiClient client;

  String jsonData = "{\"direction\":\"" + direction +
                    "\",\"type\":\"" + type +
                    "\",\"height\":" + String(height, 2) +
                    ",\"distance\":" + String(distance) + "}";

  http.begin(client, serverURL);
  http.addHeader("Content-Type", "application/json");

  int httpResponseCode = http.POST(jsonData);

  if (httpResponseCode == 200) {
    String response = http.getString();
    Serial.println("Data sent successfully: " + response);
  } else {
    Serial.print("Error sending data: ");
    Serial.println(httpResponseCode);
  }

  http.end();
}

// ====== SERIAL COMMANDS ======
void serialEvent() {
  if (Serial.available()) {
    String cmd = Serial.readStringUntil('\n');
    cmd.trim();
    if (cmd == "calibrate") calibrateRoad();
    else if (cmd == "test") testSensors();
  }
}

// ====== SENSOR TEST ======
void testSensors() {
  Serial.println("=== Sensor Test ===");
  int d1 = getDistance(trigPin1, echoPin1);
  int d2 = getDistance(trigPin2, echoPin2);
  Serial.print("Sensor1: "); Serial.print(d1); Serial.println(" cm");
  Serial.print("Sensor2: "); Serial.print(d2); Serial.println(" cm");

  lcd.clear();
  lcd.print("S1:" + String(d1) + " S2:" + String(d2));
  delay(3000);
  lcd.clear();
  lcd.print("Ready");
  lcd.setCursor(0, 1);
  lcd.print("Monitoring...");
}
