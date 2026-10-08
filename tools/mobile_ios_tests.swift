import XCTest

final class MobileChecks: XCTestCase {
    func testLivePortalLoginAndRestoredSession() throws {
        continueAfterFailure = false
        let app = XCUIApplication(bundleIdentifier: "ru.choppro.guard")
        app.launch()
        XCTAssertTrue(app.staticTexts["Вход сотрудника"].waitForExistence(timeout: 90))
        let address = app.textFields["server-url"]
        XCTAssertTrue(address.waitForExistence(timeout: 20))
        XCTAssertTrue((address.value as? String ?? "").contains("https://m20.system404-design.ru/"), "Адрес портала подставлен при запуске")
        app.buttons["Демонстрационный сотрудник"].tap()
        app.buttons["login-submit"].tap()
        let codeButton = app.buttons["Подставить код"]
        XCTAssertTrue(codeButton.waitForExistence(timeout: 90))
        codeButton.tap()
        app.buttons["login-submit"].tap()
        XCTAssertTrue(app.staticTexts["Ваш рабочий день"].waitForExistence(timeout: 120))
        let home = XCTAttachment(screenshot: app.screenshot())
        home.name = "Вход на рабочий портал"
        home.lifetime = .keepAlways
        add(home)
        app.buttons["Профиль"].tap()
        XCTAssertTrue(app.staticTexts["Ваш профиль"].waitForExistence(timeout: 20))
        app.terminate()
        app.launch()
        XCTAssertTrue(app.staticTexts["Ваш рабочий день"].waitForExistence(timeout: 90), "Сессия и зашифрованные данные восстановлены")
        app.buttons["Профиль"].tap()
        app.buttons["Выйти"].tap()
        app.alerts.buttons["Продолжить"].tap()
        XCTAssertTrue(app.staticTexts["Вход сотрудника"].waitForExistence(timeout: 40))
    }
}
