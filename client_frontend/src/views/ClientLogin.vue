<template>
  <div class="utrada-login-page">
    <!-- Header: Logo + Language -->
    <header class="login-header">
      <div class="header-logo">
        <img
          src="@/assets/utradaimg/logo1.png"
          alt="Avenlo"
          class="header-logo-img"
        />
      </div>
      <button
        type="button"
        class="language-switcher"
        :aria-expanded="showLanguageDropdown"
        @click.stop="toggleLanguageDropdown"
      >
        <span class="lang-icon">
          <i class="fas fa-globe" aria-hidden="true"></i>
        </span>
        <span class="language-text">{{ currentLanguageName }}</span>
      </button>
      <div class="language-dropdown" :class="{ active: showLanguageDropdown }">
        <button
          v-for="lang in enabledLanguages"
          :key="lang.languageCode"
          type="button"
          class="language-option"
          :class="{
            active: languageStore.currentLanguage === lang.languageCode,
          }"
          @click="changeLanguage(lang.languageCode)"
        >
          {{ lang.languageName }}
        </button>
      </div>
    </header>

    <div
      class="login-body"
      :key="
        'lang-' +
        currentLanguage +
        '-' +
        translationVersion +
        '-' +
        languageSwitchKey
      "
    >
      <!-- 依赖 translationVersion 使语言包加载后整页文案能重新渲染 -->
      <!-- Left: 登录/忘记密码 = 原样式（3+3 特性 + 大标题）；Start Trading 注册 = preview 样式（标题 + SVG 曲线与节点） -->
      <div class="login-left">
        <!-- 登录、忘记密码：原左侧 -->
        <div v-if="!isRegisterActive" class="left-content">
          <div class="features-row features-row-1">
            <div class="feature-item">
              <span class="feature-icon">
                <i class="fas fa-check" aria-hidden="true"></i>
              </span>
              <span class="feature-text">{{
                t("featureFastExecution", "Fast and reliable order execution")
              }}</span>
            </div>
            <div class="feature-item">
              <span class="feature-icon">
                <i class="fas fa-check" aria-hidden="true"></i>
              </span>
              <span class="feature-text">{{
                t(
                  "featureCompetitivePricing",
                  "Competitive pricing on global markets",
                )
              }}</span>
            </div>
            <div class="feature-item">
              <span class="feature-icon">
                <i class="fas fa-check" aria-hidden="true"></i>
              </span>
              <span class="feature-text">{{
                t("featureFunding", "Easy and secure funding solutions")
              }}</span>
            </div>
          </div>
          <h1 class="journey-title">
            <span class="line1">{{
              t("yourTradingJourney", "Your Trading Journey")
            }}</span>
            <span class="line2">{{
              t("startsWithUtrada", "Starts with Avenlo")
            }}</span>
          </h1>
          <div class="features-row features-row-2">
            <div class="feature-item">
              <span class="feature-icon">
                <i class="fas fa-check" aria-hidden="true"></i>
              </span>
              <span class="feature-text">{{
                t("featureRegulated", "Fully regulated and authorized broker")
              }}</span>
            </div>
            <div class="feature-item">
              <span class="feature-icon">
                <i class="fas fa-check" aria-hidden="true"></i>
              </span>
              <span class="feature-text">{{
                t("featureSocialTrading", "One-stop social trading platform")
              }}</span>
            </div>
            <div class="feature-item">
              <span class="feature-icon">
                <i class="fas fa-check" aria-hidden="true"></i>
              </span>
              <span class="feature-text">{{
                t("featureAcademy", "Powerful academy and market research")
              }}</span>
            </div>
          </div>
        </div>
        <!-- Start Trading 注册：preview 样式，标题 + SVG 曲线与节点（图标暂用 right.png） -->
        <div v-else class="left-content left-content--preview">
          <div class="journey-title-wrap">
            <h1 class="journey-title-preview">
              {{ t("yourTradingJourney", "Your Trading Journey") }}
            </h1>
            <h2 class="journey-subtitle-preview">
              {{ t("startsWithUtrada", "Starts with Avenlo") }}
            </h2>
          </div>
          <svg
            class="journey-svg"
            viewBox="0 0 400 800"
            preserveAspectRatio="xMidYMid meet"
          >
            <path
              class="journey-path"
              d="M80 90 C200 140 200 170 300 230 S200 370 100 420 S260 540 300 580 S200 690 120 720"
            />
            <circle class="journey-node" cx="80" cy="90" r="28" />
            <circle class="journey-node" cx="300" cy="230" r="28" />
            <circle class="journey-node" cx="100" cy="420" r="28" />
            <circle class="journey-node" cx="300" cy="580" r="28" />
            <circle class="journey-node" cx="120" cy="720" r="28" />
            <text class="journey-node-glyph" x="80" y="98">&#xf007;</text>
            <text class="journey-node-glyph" x="300" y="238">&#xf093;</text>
            <text class="journey-node-glyph" x="100" y="428">&#xf067;</text>
            <text class="journey-node-glyph" x="300" y="588">&#xf013;</text>
            <text class="journey-node-glyph" x="120" y="728">&#xf201;</text>
            <text class="journey-label" x="80" y="140">
              {{ t("personalProfile", "Personal profile") }}
            </text>
            <text class="journey-label" x="300" y="280">
              {{ t("documentUpload", "Document upload") }}
            </text>
            <text class="journey-label" x="100" y="470">
              {{ t("moreAboutYourself", "More about yourself") }}
            </text>
            <text class="journey-label" x="300" y="630">
              {{ t("accountConfiguration", "Account configuration") }}
            </text>
            <text class="journey-label" x="120" y="770">
              {{ t("startTrading", "Start Trading") }}
            </text>
          </svg>
        </div>
      </div>

      <aside class="client-auth-brand">
        <img
          src="@/assets/utradaimg/logo1.png"
          alt=""
          class="client-auth-brand-logo"
        />
        <p class="client-auth-eyebrow">
          {{ t("yourTradingJourney", "Your Trading Journey") }}
        </p>
        <h1>{{ branding.logoText || "Avenlo" }}</h1>
        <p>{{ t("featureFunding", "Easy and secure funding solutions") }}</p>
        <span class="client-auth-brand-line"></span>
      </aside>

      <!-- Right: authentication panel -->
      <div class="login-right">
        <div class="form-panel">
          <!-- Forgot Password 视图：右侧切换为填写邮箱获取重置链接 -->
          <div v-if="showForgotPassword" class="forgot-password-view">
            <h2 class="form-title">
              {{ t("resetPassword", "Reset Password") }}
            </h2>
            <form
              @submit.prevent="handleForgotPassword"
              class="login-form"
              style="margin-top: 30px"
              @input="clearTransientErrors"
              @change="clearTransientErrors"
              @focusin="clearTransientErrors"
            >
              <div class="form-group" :class="{ error: !!forgotPasswordError }">
                <label for="reset-email">{{
                  t("emailAddress", "Email address")
                }}</label>
                <input
                  type="text"
                  id="reset-email"
                  v-model="resetEmail"
                  :placeholder="
                    t('resetEmailPlaceholder', 'e.g. name@example.com')
                  "
                  :readonly="resetSuccess"
                  @input="forgotPasswordError = ''"
                />
                <small v-if="resetSuccess" class="forgot-success-hint">{{
                  t(
                    "resetEmailHint",
                    "We have sent a password reset link to your email address. Please check your inbox.",
                  )
                }}</small>
                <span v-else-if="forgotPasswordError" class="field-error">{{
                  forgotPasswordError
                }}</span>
              </div>
              <div
                v-if="!resetSuccess && error && !forgotPasswordError"
                class="general-error"
              >
                {{ error }}
              </div>
              <button
                type="submit"
                class="btn-primary"
                :disabled="loading || resetSuccess"
              >
                {{
                  loading
                    ? t("sending", "Sending...")
                    : t("sendResetLink", "Send Reset Link")
                }}
              </button>
            </form>
            <div class="back-row">
              <a
                href="#"
                class="back-to-signin"
                @click.prevent="exitForgotPassword"
                >{{ t("backToLogin", "Back to Sign In") }}</a
              >
            </div>
          </div>

          <!-- 登录/注册：tabs + 表单 -->
          <template v-else>
            <h2 class="form-title" v-if="!isRegisterActive">
              <span class="title-line1">{{
                t("welcomeTo", "Welcome to")
              }}</span>
              <span class="title-line2">{{
                branding.logoText || "Avenlo"
              }}</span>
            </h2>
            <h2 class="form-title form-title-register" v-else>
              <span class="title-line1">{{
                t("yourTradingJourney", "Your Trading Journey")
              }}</span>
              <span class="title-line2">{{
                t("startsWithUtrada", "Starts with Avenlo")
              }}</span>
            </h2>
            <div class="tabs">
              <button
                type="button"
                class="tab"
                :class="{ active: !isRegisterActive }"
                :aria-pressed="!isRegisterActive"
                @click="isRegisterActive = false"
              >
                {{ t("signIn", "Sign In") }}
              </button>
              <button
                type="button"
                class="tab"
                :class="{ active: isRegisterActive }"
                :aria-pressed="isRegisterActive"
                @click="isRegisterActive = true"
              >
                {{ t("startTrading", "Start Trading") }}
              </button>
            </div>

            <!-- Login form -->
            <form
              v-if="!isRegisterActive"
              @submit.prevent="handleLogin"
              class="login-form client-access-form"
              @input="clearTransientErrors"
              @change="clearTransientErrors"
              @focusin="clearTransientErrors"
            >
              <section
                class="client-access-section client-account-section"
                aria-labelledby="client-account-title"
              >
                <div class="client-access-heading">
                  <span class="client-access-step" aria-hidden="true">01</span>
                  <div>
                    <h3 id="client-account-title">
                      {{ t("emailAddress", "Email address") }}
                    </h3>
                    <p>
                      {{ t("welcomeTo", "Welcome to") }}
                      {{ branding.logoText || "Avenlo" }}
                    </p>
                  </div>
                </div>
                <div class="form-group" :class="{ error: errors.email }">
                  <label for="email">{{
                    t("emailAddress", "Email address")
                  }}</label>
                  <input
                    type="text"
                    id="email"
                    v-model="loginForm.email"
                    :placeholder="
                      t('emailPlaceholder', 'e.g. name@example.com')
                    "
                    @blur="validateEmail"
                    @input="errors.email = ''"
                  />
                  <span v-if="errors.email" class="field-error">{{
                    errors.email
                  }}</span>
                </div>
              </section>
              <section
                class="client-access-section client-password-section"
                aria-labelledby="client-password-title"
              >
                <div class="client-access-heading">
                  <span class="client-access-step" aria-hidden="true">02</span>
                  <div>
                    <h3 id="client-password-title">
                      {{ t("password", "Password") }}
                    </h3>
                    <p>{{ t("keepMeSignIn", "Keep me sign in") }}</p>
                  </div>
                </div>
                <div class="form-group" :class="{ error: errors.password }">
                  <div class="label-row">
                    <label for="password">{{
                      t("password", "Password")
                    }}</label>
                    <a
                      href="#"
                      class="forgot-link"
                      @click.prevent="openForgotPassword"
                      >{{ t("forgotMyPassword", "Forget my password?") }}</a
                    >
                  </div>
                  <div class="password-input-wrapper">
                    <input
                      :type="showLoginPassword ? 'text' : 'password'"
                      id="password"
                      v-model="loginForm.password"
                      :placeholder="
                        t('passwordPlaceholder', 'Enter your password')
                      "
                      @input="errors.password = ''"
                    />
                    <button
                      type="button"
                      class="password-toggle"
                      @click="showLoginPassword = !showLoginPassword"
                      :aria-label="
                        showLoginPassword
                          ? t('hidePassword', 'Hide password')
                          : t('showPassword', 'Show password')
                      "
                    >
                      <i
                        :class="
                          showLoginPassword ? 'fas fa-eye-slash' : 'fas fa-eye'
                        "
                      ></i>
                    </button>
                  </div>
                  <span v-if="errors.password" class="field-error">{{
                    errors.password
                  }}</span>
                </div>
                <div class="remember-row">
                  <input
                    type="checkbox"
                    id="remember"
                    v-model="loginForm.rememberMe"
                  />
                  <label for="remember">{{
                    t("keepMeSignIn", "Keep me sign in")
                  }}</label>
                </div>
              </section>
              <div class="client-submit-rail">
                <button
                  type="submit"
                  class="btn-primary btn-continue"
                  :disabled="loading"
                >
                  <span>{{
                    loading
                      ? t("loading", "Loading...")
                      : t("continue", "Continue")
                  }}</span>
                  <i
                    v-if="!loading"
                    class="fas fa-chevron-right btn-continue-icon"
                    aria-hidden="true"
                  ></i>
                </button>
              </div>
              <div v-if="errors.general" class="general-error">
                {{ errors.general }}
              </div>
            </form>

            <!-- Register form (design: First name, Last name, Date of birth, Email, Phone, Password, Promotion code, Create account) -->
            <form
              v-else
              @submit.prevent="handleSignup"
              class="login-form register-form"
              autocomplete="off"
              novalidate
              @input="clearTransientErrors"
              @change="clearTransientErrors"
              @focusin="clearTransientErrors"
            >
              <template v-if="enabledFormFields.length">
                <div
                  v-for="field in enabledFormFields"
                  :key="field.fieldId"
                  class="form-group"
                  :class="{
                    error:
                      signupErrors[field.fieldId] ||
                      (isPhoneField(field) && signupErrors.phone),
                  }"
                >
                  <label :for="`reg-${field.fieldId}`"
                    >{{ getFieldLabel(field)
                    }}<span v-if="field.isRequired"> *</span></label
                  >
                  <!-- 手机号：区号下拉 + 号码输入 -->
                  <div v-if="isPhoneField(field)" class="phone-input-row">
                    <CustomSelect
                      :id="`reg-${field.fieldId}-code`"
                      v-model="signupForm.phoneCountryCode"
                      class="phone-code-select"
                      :options="phoneCountryCodeOptions"
                      :placeholder="t('selectCode', 'Select code')"
                      :searchable="true"
                      :search-placeholder="t('searchCountry', 'Search country')"
                      :empty-text="t('noCountriesFound', 'No countries found')"
                      :error="Boolean(signupErrors.phone)"
                      @change="signupErrors.phone = ''"
                    />
                    <input
                      :id="`reg-${field.fieldId}`"
                      type="tel"
                      inputmode="numeric"
                      v-model="signupForm.phone"
                      :placeholder="getFieldPlaceholder(field)"
                      autocomplete="tel"
                      @input="onPhoneInput"
                    />
                  </div>
                  <span
                    v-if="isPhoneField(field) && signupErrors.phone"
                    class="field-error"
                    >{{ signupErrors.phone }}</span
                  >
                  <div
                    v-else-if="field.fieldType === 'password'"
                    class="password-input-wrapper"
                  >
                    <input
                      :type="
                        showSignupPasswords[field.fieldId] ? 'text' : 'password'
                      "
                      :id="`reg-${field.fieldId}`"
                      v-model="signupForm[field.fieldId]"
                      :placeholder="getFieldPlaceholder(field)"
                      autocomplete="new-password"
                      @input="signupErrors[field.fieldId] = ''"
                    />
                    <button
                      type="button"
                      class="password-toggle"
                      @click="toggleSignupPassword(field.fieldId)"
                      :aria-label="
                        showSignupPasswords[field.fieldId]
                          ? t('hidePassword', 'Hide password')
                          : t('showPassword', 'Show password')
                      "
                    >
                      <i
                        :class="
                          showSignupPasswords[field.fieldId]
                            ? 'fas fa-eye-slash'
                            : 'fas fa-eye'
                        "
                      ></i>
                    </button>
                  </div>
                  <input
                    v-else-if="
                      field.fieldType !== 'select' && !isPhoneField(field)
                    "
                    :type="
                      field.fieldType === 'date' || field.fieldType === 'email'
                        ? 'text'
                        : field.fieldType
                    "
                    :id="`reg-${field.fieldId}`"
                    v-model="signupForm[field.fieldId]"
                    :placeholder="getFieldPlaceholder(field)"
                    autocomplete="off"
                    @input="signupErrors[field.fieldId] = ''"
                  />
                  <!-- 仅 Country of Residence 等非手机字段显示国家下拉；Phone Number 只用区号+号码，不显示 Select your country -->
                  <CustomSelect
                    v-else-if="
                      field.fieldType === 'select' && !isPhoneField(field)
                    "
                    :id="`reg-${field.fieldId}`"
                    v-model="signupForm[field.fieldId]"
                    class="register-country-select"
                    :options="countryOptions"
                    :placeholder="t('selectCountry', 'Select your country')"
                    :searchable="true"
                    :search-placeholder="t('searchCountry', 'Search country')"
                    :empty-text="t('noCountriesFound', 'No countries found')"
                    :error="Boolean(signupErrors[field.fieldId])"
                    @change="signupErrors[field.fieldId] = ''"
                  />
                  <small
                    v-if="field.fieldId === 'password'"
                    class="password-hint"
                    >{{ passwordRequirementText }}</small
                  >
                  <span
                    v-if="!isPhoneField(field) && signupErrors[field.fieldId]"
                    class="field-error"
                    >{{ signupErrors[field.fieldId] }}</span
                  >
                </div>
              </template>
              <p v-else class="form-hint">
                {{
                  t(
                    "registrationFormLoading",
                    "Registration form is loading...",
                  )
                }}
              </p>
              <div v-if="enabledFormFields.length" class="terms-checkbox-wrap">
                <div
                  class="form-group terms-checkbox"
                  :class="{ error: signupErrors.agreeTerms }"
                >
                  <div class="terms-checkbox-block">
                    <label class="terms-label">
                      <input type="checkbox" v-model="signupForm.agreeTerms" />
                      <span
                        >{{
                          t("agreeTermsPrefix", "I have read and agree to the")
                        }}
                        <template v-if="legalDocuments.length"
                          ><span
                            v-for="(doc, i) in legalDocuments"
                            :key="doc.id"
                            ><a
                              href="#"
                              @click.prevent="openLegalDocument(doc)"
                              >{{ doc.title }}</a
                            >{{
                              i < legalDocuments.length - 1 ? ", " : ""
                            }}</span
                          ></template
                        ><template v-else>{{
                          t("termsAndConditions", "Terms and Conditions")
                        }}</template
                        >.</span
                      >
                    </label>
                  </div>
                </div>
                <span
                  v-if="signupErrors.agreeTerms"
                  class="field-error terms-field-error"
                  >{{ signupErrors.agreeTerms }}</span
                >
              </div>
              <button
                v-if="enabledFormFields.length"
                type="submit"
                class="btn-primary btn-continue"
                :disabled="loading"
              >
                <span>{{
                  loading
                    ? t("creatingAccount", "Creating Account...")
                    : t("createAccount", "Create Account")
                }}</span>
                <i
                  v-if="!loading"
                  class="fas fa-chevron-right btn-continue-icon"
                  aria-hidden="true"
                ></i>
              </button>
              <div v-if="errors.general" class="general-error">
                {{ errors.general }}
              </div>
              <div v-if="signupSuccess" class="success-message">
                <span v-if="emailVerificationRequired">
                  <strong>{{
                    t("registrationPendingVerification", "Registration Pending")
                  }}</strong
                  ><br />
                  {{
                    t(
                      "pleaseVerifyEmail",
                      "Please verify your email to complete registration.",
                    )
                  }}
                </span>
                <span v-else>
                  <strong>{{
                    t("registrationSuccessful", "Registration Successful!")
                  }}</strong
                  ><br />
                  {{
                    t(
                      "accountReadyMessage",
                      "Your account is ready! You can now log in.",
                    )
                  }}
                </span>
              </div>
            </form>
          </template>
        </div>
      </div>
    </div>
  </div>

  <!-- Sign Up Modal -->
  <div v-if="activeModal === 'signup'" class="modal" @click.self="closeModal">
    <div class="modal-content">
      <div class="modal-header">
        <button
          type="button"
          class="close"
          :aria-label="t('close', 'Close')"
          @click="closeModal"
        >
          &times;
        </button>
        <h2>{{ t("signUp", "Sign Up") }}</h2>
      </div>
      <div class="modal-body">
        <form
          @submit.prevent="handleSignup"
          v-if="!signupSuccess"
          autocomplete="off"
          novalidate
          @input="clearTransientErrors"
          @change="clearTransientErrors"
          @focusin="clearTransientErrors"
        >
          <div
            v-for="field in enabledFormFields"
            :key="field.fieldId"
            class="modal-form-group"
          >
            <label :for="`signup-${field.fieldId}`">
              {{ getFieldLabel(field) }}
              <span v-if="field.isRequired" class="required">*</span>
            </label>

            <!-- Password fields with toggle -->
            <div v-if="field.fieldType === 'password'">
              <div class="password-input-wrapper">
                <input
                  :type="
                    showSignupPasswords[field.fieldId] ? 'text' : 'password'
                  "
                  :id="`signup-${field.fieldId}`"
                  v-model="signupForm[field.fieldId]"
                  :placeholder="getFieldPlaceholder(field)"
                  autocomplete="new-password"
                />
                <button
                  type="button"
                  class="password-toggle"
                  @click="toggleSignupPassword(field.fieldId)"
                  :aria-label="
                    showSignupPasswords[field.fieldId]
                      ? t('hidePassword', 'Hide password')
                      : t('showPassword', 'Show password')
                  "
                >
                  <i
                    :class="
                      showSignupPasswords[field.fieldId]
                        ? 'fas fa-eye-slash'
                        : 'fas fa-eye'
                    "
                  ></i>
                </button>
              </div>
              <!-- Password strength requirement hint (only for main password field) -->
              <small
                v-if="field.fieldId === 'password'"
                class="password-requirement-hint"
              >
                <i class="fas fa-info-circle"></i> {{ passwordRequirementText }}
              </small>
            </div>

            <!-- Non-password input fields -->
            <input
              v-else-if="field.fieldType !== 'select'"
              :type="field.fieldType === 'email' ? 'text' : field.fieldType"
              :id="`signup-${field.fieldId}`"
              v-model="signupForm[field.fieldId]"
              :placeholder="getFieldPlaceholder(field)"
              autocomplete="off"
            />

            <!-- Select fields -->
            <div
              v-else
              :ref="setSignupSelectRef(field.fieldId)"
              class="modal-custom-select"
            >
              <button
                :id="`signup-${field.fieldId}`"
                type="button"
                :class="[
                  'modal-custom-select-trigger',
                  {
                    placeholder: !signupForm[field.fieldId],
                    open: activeSignupSelect === field.fieldId,
                  },
                ]"
                @click="toggleSignupSelect(field.fieldId)"
              >
                <span>{{
                  getSignupSelectLabel(field) ||
                  t("selectCountry", "Select your country")
                }}</span>
                <i class="fas fa-chevron-down"></i>
              </button>
              <div
                v-if="activeSignupSelect === field.fieldId"
                class="modal-custom-select-menu"
              >
                <div class="modal-custom-select-search">
                  <i class="fas fa-search"></i>
                  <input
                    v-model="signupSelectSearch"
                    type="text"
                    :placeholder="t('searchCountry', 'Search country')"
                  />
                </div>
                <div class="modal-custom-select-options">
                  <button
                    v-for="country in filteredSignupSelectOptions"
                    :key="country.code"
                    type="button"
                    :class="[
                      'modal-custom-select-option',
                      { selected: signupForm[field.fieldId] === country.code },
                    ]"
                    @click="selectSignupOption(field.fieldId, country.code)"
                  >
                    {{ country.name }}
                  </button>
                  <div
                    v-if="filteredSignupSelectOptions.length === 0"
                    class="modal-custom-select-empty"
                  >
                    {{ t("noCountriesFound", "No countries found") }}
                  </div>
                </div>
              </div>
            </div>
            <small v-if="field.fieldDescription">{{
              field.fieldDescription
            }}</small>
          </div>

          <div class="terms-checkbox">
            <input
              type="checkbox"
              id="agree-terms"
              v-model="signupForm.agreeTerms"
            />
            <label for="agree-terms">
              {{ t("agreeTermsPrefix", "I have read and agree to the") }}
              <span v-for="(doc, index) in legalDocuments" :key="doc.id">
                <template v-if="index > 0">
                  <span v-if="index === legalDocuments.length - 1">
                    {{ t("and", "and") }}
                  </span>
                  <span v-else>, </span>
                </template>

                <a href="#" @click.prevent="openLegalDocument(doc)">
                  {{ doc.title }}
                </a> </span
              >.
            </label>
          </div>

          <button
            type="submit"
            class="modal-button"
            :disabled="loading"
            @click="clearTransientErrors"
          >
            {{
              loading
                ? t("creatingAccount", "Creating Account...")
                : t("signUp", "Sign Up")
            }}
          </button>
        </form>

        <div v-if="signupSuccess" class="success-message">
          <span v-if="emailVerificationRequired">
            <strong>{{
              t("registrationPendingVerification", "Registration Pending")
            }}</strong
            ><br />
            {{
              t(
                "pleaseVerifyEmail",
                "Please verify your email to complete registration.",
              )
            }}
          </span>
          <span v-else>
            <strong>{{
              t("registrationSuccessful", "Registration Successful!")
            }}</strong
            ><br />
            {{
              t(
                "accountReadyMessage",
                "Your account is ready! You can now log in.",
              )
            }}
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Legal Document Modal：无顶栏，仅右上角 ×，正文标题居中，底部 Close 居右 -->
  <div
    v-if="activeLegalDoc"
    class="modal legal-modal"
    @click.self="closeLegalDocument"
  >
    <div class="modal-content legal-content">
      <button
        type="button"
        class="legal-modal-close"
        @click="closeLegalDocument"
        :aria-label="t('close', 'Close')"
      >
        &times;
      </button>
      <div class="modal-body legal-body">
        <div
          class="legal-document-content"
          v-html="activeLegalDoc.content"
        ></div>
        <div class="legal-footer">
          <button
            type="button"
            class="legal-footer-btn"
            @click="closeLegalDocument"
          >
            {{ t("close", "Close") }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Error Notification (fallback for modal/global errors) -->
  <div
    v-if="error && activeModal"
    class="error-notification"
    @click="clearTransientErrors"
  >
    {{ error }}
    <button
      type="button"
      @click.stop="clearTransientErrors"
      class="close-btn"
      :aria-label="t('close', 'Close')"
    >
      &times;
    </button>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch, onUnmounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useClientAuthStore } from "@/stores/clientAuth";
import { useLanguageStore } from "@/stores/language";
import loginSettingsService from "@/services/loginSettingsService";
import brandingApi from "@/services/brandingApi";
import { useCountryStore } from "@/stores/countryStore";
import CustomSelect from "@/components/common/CustomSelect.vue";

const router = useRouter();
const route = useRoute();
const clientAuthStore = useClientAuthStore();
const languageStore = useLanguageStore();
const countryStore = useCountryStore();

// State
const branding = ref({
  logoType: "text",
  logoText: "CRM",
  logoImagePath: null,
  taglineEn: "Advanced CFD Trading Platform",
  featuresContent:
    "<ul><li>Trade CFDs on Forex, Stocks, and Commodities</li></ul>",
});

// Branding configuration (for fallback)
const appBranding = ref({
  logoText: "CRM",
  companyName: "Trading Platform",
  copyrightText: "Trading Platform",
});
const showLanguageDropdown = ref(false);
const activeModal = ref(null);
const loading = ref(false);
const error = ref("");
const resetEmail = ref("");
const resetSuccess = ref(false);
const signupSuccess = ref(false);
const enabledFormFields = ref([]);
const legalDocuments = ref([]);
const activeLegalDoc = ref(null);
const showLoginPassword = ref(false);
const showSignupPasswords = ref({});
const errors = ref({ email: "", password: "", general: "" });
const signupErrors = ref({});
const languageSwitchKey = ref(0); // 切换语言后递增，使 :key 变化以强制占位符等重新渲染
const isRegisterActive = ref(false);
const showForgotPassword = ref(false);
const forgotPasswordError = ref("");
const emailVerificationRequired = ref(true); // 默认需要邮箱验证
const passwordStrengthSettings = ref({
  strengthLevel: "medium",
  minLength: 8,
  requireLetters: true,
  requireNumbers: true,
  requireUppercase: false,
  requireLowercase: false,
  requireSpecialChars: false,
  description: "Minimum 8 characters with letters and numbers",
});
const transientErrorTimer = ref(null);
const signupErrorTimer = ref(null);
const signupSelectRefs = ref({});
const activeSignupSelect = ref("");
const signupSelectSearch = ref("");

const loginForm = ref({
  email: "",
  password: "",
  rememberMe: false,
});

const REMEMBER_EMAIL_KEY = "client_remembered_email";

const signupForm = ref({
  email: "",
  password: "",
  confirmPassword: "",
  agreeTerms: false,
});

// Computed - 使用全局 language store（依赖 translations 以在语言包加载后触发重新渲染）
const currentLanguageName = computed(() => languageStore.currentLanguageName);
const enabledLanguages = computed(() => languageStore.enabledLanguages);
const currentLanguage = computed(() => languageStore.currentLanguage);
const countryOptions = computed(() => countryStore.countries);
const phoneCountries = computed(() =>
  (countryStore.countries || []).filter((c) => c.phoneCode),
);
const phoneCountryCodeOptions = computed(() => {
  if (!phoneCountries.value.length) return [{ label: "+86", value: "+86" }];
  return phoneCountries.value.map((country) => ({
    label: `${country.phoneCode}${country.name ? ` · ${country.name}` : ""}`,
    value: country.phoneCode,
  }));
});
const translationVersion = computed(
  () => Object.keys(languageStore.translations || {}).length,
);
const filteredSignupSelectOptions = computed(() => {
  const keyword = signupSelectSearch.value.trim().toLowerCase();
  if (!keyword) return countryOptions.value;
  return countryOptions.value.filter((country) =>
    String(country.name || "")
      .toLowerCase()
      .includes(keyword),
  );
});

// 生成密码要求提示文本（使用 t 以支持中英文等）
const passwordRequirementText = computed(() => {
  const settings = passwordStrengthSettings.value;
  const requirements = [];
  requirements.push(
    `${t("minimum", "Minimum")} ${settings.minLength} ${t("characters", "characters")}`,
  );
  if (settings.requireLetters) requirements.push(t("letters", "letters"));
  if (settings.requireNumbers) requirements.push(t("numbers", "numbers"));
  if (settings.requireUppercase && settings.requireLowercase)
    requirements.push(t("uppercaseAndLowercase", "uppercase and lowercase"));
  else if (settings.requireUppercase)
    requirements.push(t("uppercaseLetters", "uppercase letters"));
  else if (settings.requireLowercase)
    requirements.push(t("lowercaseLetters", "lowercase letters"));
  if (settings.requireSpecialChars)
    requirements.push(t("specialCharacters", "special characters"));
  if (requirements.length <= 1) return requirements[0] || "";
  const last = requirements.pop();
  return requirements.join(", ") + " " + t("and", "and") + " " + last;
});

// Translation helper - 使用全局 store 的翻译函数
const t = (key, fallback = "") => languageStore.t(key, fallback);

/** 注册表单项标签：已知 fieldId 用语言包翻译，其余用后台 fieldName */
const getFieldLabel = (field) => {
  const id = (field.fieldId || "").toLowerCase();
  if (id === "email") return t("emailAddress", "Email Address");
  if (id === "password") return t("password", "Password");
  if (id === "confirmpassword") return t("confirmPassword", "Confirm Password");
  if (id === "firstname" || id === "first_name")
    return t("firstName", "First Name");
  if (id === "lastname" || id === "last_name")
    return t("lastName", "Last Name");
  if (id === "phone" || id === "phonenumber" || id === "mobile")
    return t("phoneNumber", "Phone Number");
  if (id === "country") return t("country", "Country of Residence");
  if (id === "dateofbirth" || id === "dob")
    return t("dateOfBirth", "Date of Birth");
  return field.fieldName || "";
};

/** 注册表单项占位符：已知 fieldId 始终用语言包以随语言切换，未知字段才用后台 fieldDescription */
const getFieldPlaceholder = (field) => {
  const id = (field.fieldId || "").toLowerCase();
  if (id === "email") return t("emailPlaceholder", "e.g. name@example.com");
  if (id === "password") return t("passwordPlaceholder", "Enter your password");
  if (id === "confirmpassword")
    return t("confirmNewPassword", "Confirm new password");
  if (id === "firstname" || id === "first_name")
    return t("asShownOnId", "As shown on your ID");
  if (id === "lastname" || id === "last_name")
    return t("asShownOnId", "As shown on your ID");
  if (id === "phone" || id === "phonenumber" || id === "mobile")
    return t("phonePlaceholder", "Phone number");
  if (id === "dateofbirth" || id === "dob")
    return t("datePlaceholder", "mm/dd/yyyy");
  return field.fieldDescription || "";
};

const isPhoneField = (field) => {
  const id = (field.fieldId || "").toLowerCase();
  const name = (field.fieldName || "").toLowerCase();
  return (
    id === "phone" ||
    id === "phonenumber" ||
    id === "mobile" ||
    name.includes("phone") ||
    name.includes("mobile")
  );
};

const onPhoneInput = () => {
  signupErrors.value.phone = "";
  const raw = signupForm.value.phone || "";
  const digits = raw.replace(/\D/g, "");
  if (raw !== digits) signupForm.value.phone = digits;
};

// Methods
const toggleLanguageDropdown = () => {
  showLanguageDropdown.value = !showLanguageDropdown.value;
};

const setSignupSelectRef = (fieldId) => (el) => {
  if (el) signupSelectRefs.value[fieldId] = el;
  else delete signupSelectRefs.value[fieldId];
};

const toggleSignupSelect = (fieldId) => {
  const nextOpen = activeSignupSelect.value === fieldId ? "" : fieldId;
  activeSignupSelect.value = nextOpen;
  if (!nextOpen) {
    signupSelectSearch.value = "";
  }
};

const closeSignupSelect = () => {
  activeSignupSelect.value = "";
  signupSelectSearch.value = "";
};

const selectSignupOption = (fieldId, value) => {
  signupForm.value[fieldId] = value;
  closeSignupSelect();
};

const getSignupSelectLabel = (field) => {
  if (field.fieldType !== "select") return "";
  const selected = countryOptions.value.find(
    (country) => country.code === signupForm.value[field.fieldId],
  );
  return selected?.name || "";
};

const changeLanguage = async (langCode) => {
  showLanguageDropdown.value = false;
  await languageStore.changeLanguage(langCode);
  // 切换语言后清空所有错误信息，避免旧语言错误文案残留
  errors.value = { email: "", password: "", general: "" };
  signupErrors.value = {};
  forgotPasswordError.value = "";
  error.value = "";
  languageSwitchKey.value += 1; // 强制登录/注册区（含占位符）按新语言重新渲染
  loadLegalDocuments(); // 重新加载对应语言的法律文档
};

const shouldOpenSignup = () => {
  const mode = String(route.query.mode || "").toLowerCase();
  return mode === "signup" || mode === "register" || mode === "registration";
};

const activateRegisterForm = () => {
  closeSignupSelect();
  activeModal.value = null;
  showForgotPassword.value = false;
  resetSuccess.value = false;
  signupSuccess.value = false;
  error.value = "";
  isRegisterActive.value = true;
};

const openModal = (modalName) => {
  closeSignupSelect();
  activeModal.value = modalName;
  resetSuccess.value = false;
  signupSuccess.value = false;
  error.value = "";
};

const openForgotPassword = () => {
  showForgotPassword.value = true;
  resetSuccess.value = false;
  error.value = "";
  forgotPasswordError.value = "";
  resetEmail.value = "";
};

const exitForgotPassword = () => {
  showForgotPassword.value = false;
  resetEmail.value = "";
  error.value = "";
  forgotPasswordError.value = "";
  resetSuccess.value = false;
};

const closeModal = () => {
  closeSignupSelect();
  activeModal.value = null;
  resetEmail.value = "";
  errors.value = { email: "", password: "", general: "" };
  clearTransientErrorTimer();

  // 重置注册表单 - 清空所有字段
  const resetForm = {
    agreeTerms: false,
  };

  enabledFormFields.value.forEach((field) => {
    resetForm[field.fieldId] = "";
  });
  if (enabledFormFields.value.some((f) => isPhoneField(f))) {
    const first = phoneCountries.value[0];
    resetForm.phoneCountryCode = first ? first.phoneCode : "+86";
    resetForm.phone = "";
  }

  signupForm.value = resetForm;
};

function validateEmail() {
  const email = loginForm.value.email?.trim();
  if (!email) return;
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!re.test(email)) {
    errors.value.email = t(
      "invalidEmail",
      "Please enter a valid email address.",
    );
  } else {
    errors.value.email = "";
  }
}

const clearTransientErrorTimer = () => {
  if (transientErrorTimer.value) {
    clearTimeout(transientErrorTimer.value);
    transientErrorTimer.value = null;
  }
};

const clearTransientErrors = () => {
  error.value = "";
  forgotPasswordError.value = "";
  errors.value.general = "";
  clearTransientErrorTimer();
};

const scheduleTransientErrorClear = () => {
  clearTransientErrorTimer();
  transientErrorTimer.value = setTimeout(() => {
    clearTransientErrors();
    transientErrorTimer.value = null;
  }, 10000);
};

const handleLogin = async () => {
  errors.value = { email: "", password: "", general: "" };
  const email = loginForm.value.email?.trim();
  const password = loginForm.value.password;

  if (!email) {
    errors.value.email = t("emailRequired", "Please enter your email address.");
    return;
  }
  const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRe.test(email)) {
    errors.value.email = t(
      "invalidEmail",
      "Please enter a valid email address.",
    );
    return;
  }
  if (!password) {
    errors.value.password = t(
      "passwordRequired",
      "Please enter your password.",
    );
    return;
  }

  loading.value = true;
  error.value = "";

  const result = await clientAuthStore.login(loginForm.value);

  if (result.success) {
    const pendingInvitation = sessionStorage.getItem("pendingIbInvitation");
    if (pendingInvitation) {
      router.push(`/ib/invitation/${pendingInvitation}`);
    } else {
      router.push("/client/dashboard");
    }
  } else {
    const msg = result.error || "";
    // 密码错误：显示在密码框下方
    if (
      msg.includes("Please enter a valid password") ||
      /valid\s+password/i.test(msg)
    ) {
      errors.value.password = msg;
      // 邮箱相关（未验证/已注册请查邮件、或账号不存在）：显示在邮箱框下方
    } else if (
      msg.includes("This email address has been registered") ||
      msg.includes("check your email inbox") ||
      msg.includes("complete your registration") ||
      msg.includes("No account found for this email")
    ) {
      errors.value.email = msg;
    } else {
      errors.value.general = msg;
      scheduleTransientErrorClear();
    }
  }

  loading.value = false;
};

const handleForgotPassword = async () => {
  const email = resetEmail.value?.trim();
  if (!email) {
    forgotPasswordError.value = t(
      "emailRequired",
      "Please enter your email address.",
    );
    return;
  }
  const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRe.test(email)) {
    forgotPasswordError.value = t(
      "invalidEmail",
      "Please enter a valid email address.",
    );
    return;
  }
  loading.value = true;
  error.value = "";
  forgotPasswordError.value = "";
  resetSuccess.value = false;

  try {
    const result = await clientAuthStore.forgotPassword(email);
    if (result.success) {
      resetSuccess.value = true;
      error.value = "";
      forgotPasswordError.value = "";
    } else {
      const msg =
        result.error || "Failed to send reset link. Please try again.";
      forgotPasswordError.value = msg;
      error.value = "";
      resetSuccess.value = false;
      scheduleTransientErrorClear();
    }
  } catch (err) {
    const msg =
      err.message ||
      err.error ||
      "Failed to send reset link. Please try again.";
    forgotPasswordError.value = msg;
    error.value = "";
    resetSuccess.value = false;
    scheduleTransientErrorClear();
  } finally {
    loading.value = false;
  }
};

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const handleSignup = async () => {
  loading.value = true;
  error.value = "";
  signupErrors.value = {};

  // 前端校验：必填、邮箱格式、密码一致、勾选条款（不用浏览器自带验证，用页面样式展示错误）
  for (const field of enabledFormFields.value) {
    if (!field.isRequired) continue;
    if (isPhoneField(field)) {
      const num = (signupForm.value.phone || "").trim();
      if (!num)
        signupErrors.value.phone = t(
          "fieldRequired",
          "This field is required.",
        );
    } else {
      const val = signupForm.value[field.fieldId];
      const empty =
        val === undefined || val === null || String(val).trim() === "";
      if (empty) {
        signupErrors.value[field.fieldId] = t(
          "fieldRequired",
          "This field is required.",
        );
        continue;
      }
      if (field.fieldType === "email" || field.fieldId === "email") {
        if (!EMAIL_REGEX.test(String(val).trim())) {
          signupErrors.value[field.fieldId] = t(
            "invalidEmail",
            "Please enter a valid email address.",
          );
        }
      }
    }
  }
  if (!signupForm.value.agreeTerms) {
    signupErrors.value.agreeTerms = t(
      "agreeTermsRequired",
      "Please read and agree to the terms.",
    );
  }
  if (signupForm.value.password !== signupForm.value.confirmPassword) {
    signupErrors.value.confirmPassword = t(
      "passwordsDoNotMatch",
      "Passwords do not match.",
    );
  }

  if (Object.keys(signupErrors.value).length > 0) {
    loading.value = false;
    return;
  }

  const payload = { ...signupForm.value };
  if (enabledFormFields.value.some((f) => isPhoneField(f))) {
    const code = signupForm.value.phoneCountryCode || "";
    const num = (signupForm.value.phone || "").trim();
    payload.phone = code ? code + num : num;
  }
  try {
    const ref = sessionStorage.getItem("salesReferralRef");
    if (ref) payload.ref = ref;
    const ibRef = sessionStorage.getItem("ibReferralRef");
    if (ibRef) payload.ibRef = ibRef;
  } catch (e) {}

  const result = await clientAuthStore.register(payload);

  if (result.success) {
    try {
      sessionStorage.removeItem("salesReferralRef");
      sessionStorage.removeItem("ibReferralRef");
    } catch (e) {}
    signupSuccess.value = true;
    setTimeout(() => {
      signupSuccess.value = false;
      isRegisterActive.value = false;
      const resetForm = { agreeTerms: false };
      enabledFormFields.value.forEach((field) => {
        resetForm[field.fieldId] = "";
      });
      if (enabledFormFields.value.some((f) => isPhoneField(f))) {
        const first = phoneCountries.value[0];
        resetForm.phoneCountryCode = first ? first.phoneCode : "+86";
        resetForm.phone = "";
      }
      signupForm.value = resetForm;
    }, 3000);
  } else {
    error.value = result.error || "";
    errors.value.general = result.error || "";
    scheduleTransientErrorClear();
  }

  loading.value = false;
};

const loadRememberedEmail = () => {
  if (typeof window === "undefined") return;
  const savedEmail = localStorage.getItem(REMEMBER_EMAIL_KEY);
  if (savedEmail) {
    loginForm.value.email = savedEmail;
    loginForm.value.rememberMe = true;
  }
  // 恢复上次「Keep me sign in」的勾选状态
  if (localStorage.getItem("clientRememberMe") === "true") {
    loginForm.value.rememberMe = true;
  }
};

// Load initial data
const loadBranding = async () => {
  try {
    // 先加载应用品牌配置（作为fallback）
    try {
      const appConfig = await brandingApi.getBranding();
      appBranding.value = {
        logoText: appConfig.logoText || "CRM",
        companyName: appConfig.companyName || "Trading Platform",
        copyrightText: appConfig.copyrightText || "Trading Platform",
      };
      // 如果loginSettings没有logoText，使用appBranding的logoText
      if (!branding.value.logoText || branding.value.logoText === "CRM") {
        branding.value.logoText = appBranding.value.logoText;
      }
    } catch (err) {
      console.error("Failed to load app branding:", err);
    }

    // 然后加载登录页面的品牌设置
    const response = await loginSettingsService.getBranding();
    // console.log('Branding response:', response)
    if (response.success && response.data) {
      branding.value = {
        ...branding.value,
        ...response.data,
      };
      if (
        ["UTrada", "UTrada CRM", "UTrada CRM Demo"].includes(
          branding.value.logoText?.trim(),
        )
      ) {
        branding.value.logoText = appBranding.value.logoText;
      }
    }
  } catch (err) {
    console.error("Failed to load branding:", err);
    // 保持默认值，不显示错误给用户
  }
};

// 语言加载已移至全局 store

const loadFormFields = async () => {
  try {
    const response = await loginSettingsService.getEnabledFormFields();
    // console.log('Form fields response:', response)
    if (response.success && response.data && Array.isArray(response.data)) {
      enabledFormFields.value = response.data;

      // 初始化signupForm的字段
      enabledFormFields.value.forEach((field) => {
        if (!signupForm.value[field.fieldId]) {
          signupForm.value[field.fieldId] = "";
        }
      });
      if (
        enabledFormFields.value.some((f) => isPhoneField(f)) &&
        !signupForm.value.phoneCountryCode
      ) {
        const first = phoneCountries.value[0];
        signupForm.value.phoneCountryCode = first ? first.phoneCode : "+86";
      }
    }
  } catch (err) {
    console.error("Failed to load form fields:", err);
    // 保持默认字段
  }
};

// 条款接口：GET /api/login-settings/legal-documents/active?lang=xx，数据表：legalDocuments（字段含 id, title, content, languageCode, isActive, displayOrder 等）
const loadLegalDocuments = async () => {
  try {
    let response = await loginSettingsService.getActiveLegalDocuments(
      languageStore.currentLanguage,
    );
    const data = response?.data;
    if (response?.success && Array.isArray(data) && data.length > 0) {
      legalDocuments.value = data;
      return;
    }
    // 当前语言无数据时用 en 兜底（后台常只配置 en）
    if (languageStore.currentLanguage !== "en") {
      response = await loginSettingsService.getActiveLegalDocuments("en");
      if (response?.success && Array.isArray(response.data)) {
        legalDocuments.value = response.data;
        return;
      }
    }
    legalDocuments.value = Array.isArray(data) ? data : [];
  } catch (err) {
    console.error("Failed to load legal documents:", err);
    legalDocuments.value = [];
  }
};

const loadEmailVerificationSettings = async () => {
  try {
    const response = await loginSettingsService.getEmailVerification();
    // console.log('Email verification settings response:', response)
    if (response.success && response.data) {
      emailVerificationRequired.value = response.data.isRequired !== false;
    }
  } catch (err) {
    console.error("Failed to load email verification settings:", err);
    // 默认需要验证
    emailVerificationRequired.value = true;
  }
};

const loadPasswordStrengthSettings = async () => {
  try {
    const response = await loginSettingsService.getPasswordStrength();
    // console.log('Password strength settings response:', response)
    if (response.success && response.data) {
      passwordStrengthSettings.value = response.data;
    }
  } catch (err) {
    console.error("Failed to load password strength settings:", err);
    // 保持默认值
  }
};

const openLegalDocument = (doc) => {
  activeLegalDoc.value = doc;
};

const closeLegalDocument = () => {
  activeLegalDoc.value = null;
};

const toggleSignupPassword = (fieldId) => {
  showSignupPasswords.value[fieldId] = !showSignupPasswords.value[fieldId];
};

// Logo URL helper
const getLogoUrl = (logoPath) => {
  if (!logoPath) return "";

  // 如果是完整URL，直接返回
  if (logoPath.startsWith("http://") || logoPath.startsWith("https://")) {
    return logoPath;
  }

  // 从 API base URL 中提取后端基础路径
  // API URL 格式：http://localhost/Utrada%20CRM/back-end/index.php?path=api
  // 我们需要：http://localhost/Utrada%20CRM/back-end
  let baseUrl =
    import.meta.env.VITE_API_BASE_URL ||
    "http://localhost/Utrada%20CRM/back-end/index.php?path=api";

  // 移除 index.php?path=... 部分
  if (baseUrl.includes("index.php")) {
    baseUrl = baseUrl.split("index.php")[0].replace(/\/$/, "");
  }

  // 去掉 logoPath 开头的斜杠（如果有）
  const cleanPath = logoPath.startsWith("/") ? logoPath.substring(1) : logoPath;

  const finalUrl = `${baseUrl}/${cleanPath}`;
  console.log("Logo URL:", { logoPath, baseUrl, cleanPath, finalUrl });

  return finalUrl;
};

// 处理logo图片加载失败
const handleLogoError = (event) => {
  console.error("Failed to load logo image:", {
    logoPath: branding.value.logoImagePath,
    attemptedUrl: event.target.src,
    logoType: branding.value.logoType,
  });
  // 加载失败时，回退到文本logo
  branding.value.logoType = "text";
};

onMounted(async () => {
  loadRememberedEmail();

  // 不依赖语言的数据先发出去。这些请求以前排在语言初始化之后，
  // 在高延迟链路上会让注册表单长时间停在 "Registration form is loading..."。
  if (!countryStore.loaded) {
    countryStore.fetchCountries();
  }
  loadBranding();
  loadFormFields();
  loadEmailVerificationSettings();
  loadPasswordStrengthSettings();

  if (shouldOpenSignup()) {
    activateRegisterForm();
  }

  // 初始化全局语言系统（与 Dashboard 等页一致：用 public 语言包做翻译）
  await languageStore.initLanguage();

  // 法律文档按当前语言取，必须等语言确定后再加载
  loadLegalDocuments();
});

onUnmounted(() => {
  clearTransientErrorTimer();
});

watch(
  () => loginForm.value.rememberMe,
  (isChecked) => {
    if (typeof window === "undefined") return;
    if (!isChecked) {
      localStorage.removeItem(REMEMBER_EMAIL_KEY);
      return;
    }
    const email = loginForm.value.email.trim();
    if (email) {
      localStorage.setItem(REMEMBER_EMAIL_KEY, email);
    } else {
      localStorage.removeItem(REMEMBER_EMAIL_KEY);
    }
  },
);

watch(
  () => loginForm.value.email,
  (email) => {
    if (typeof window === "undefined") return;
    if (!loginForm.value.rememberMe) return;
    const trimmed = email.trim();
    if (trimmed) {
      localStorage.setItem(REMEMBER_EMAIL_KEY, trimmed);
    } else {
      localStorage.removeItem(REMEMBER_EMAIL_KEY);
    }
  },
);

watch(
  () => route.query.mode,
  () => {
    if (shouldOpenSignup()) {
      activateRegisterForm();
    }
  },
);

// Close dropdown when clicking outside
if (typeof window !== "undefined") {
  document.addEventListener("click", (e) => {
    if (
      !e.target.closest(".language-switcher") &&
      !e.target.closest(".language-dropdown")
    ) {
      showLanguageDropdown.value = false;
    }

    if (activeSignupSelect.value) {
      const currentSelect = signupSelectRefs.value[activeSignupSelect.value];
      if (currentSelect && !currentSelect.contains(e.target)) {
        closeSignupSelect();
      }
    }
  });
}
</script>

<style scoped>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

.utrada-login-page {
  min-height: 100vh;
  background: var(--color-sidebar);
  display: flex;
  flex-direction: column;
  width: 100%;
  font-family: "Work Sans", sans-serif;
}

/* ---------- Header: 64px, padding 132px (Figma) ---------- */
.login-header {
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 132px;
  flex-shrink: 0;
  background: var(--color-sidebar);
  position: relative;
}

.header-logo-img {
  height: 32px;
  width: auto;
  max-width: 180px;
  object-fit: contain;
  display: block;
}

.language-switcher {
  border: 0;
  background: transparent;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 0;
  cursor: pointer;
  color: rgba(255, 255, 255, 0.9);
  font-size: 14px;
}

.ang-iconl {
  font-size: 18px;
}
.lang-icon-img {
  width: 20px;
  height: 20px;
  display: inline-block;
  vertical-align: middle;
}

.language-dropdown {
  position: absolute;
  top: 68px;
  right: 132px;
  background: #fff;
  border-radius: var(--radius-md);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
  overflow: hidden;
  display: none;
  min-width: 160px;
  z-index: 100;
}

.language-dropdown.active {
  display: block;
}

.language-option {
  display: block;
  width: 100%;
  border: 0;
  background: transparent;
  text-align: left;
  padding: 12px 16px;
  cursor: pointer;
  color: var(--color-text);
  font-size: 14px;
}

.language-option:hover {
  background: var(--color-surface-soft);
}

.language-option.active {
  background: var(--color-brand-soft);
  color: var(--color-brand);
  font-weight: 600;
}

/* ---------- Body: 132px padding, 62px gap, left 580px right 530px (Figma) ---------- */
.login-body {
  flex: 1;
  display: flex;
  min-height: calc(100vh - 64px);
  padding: 0 132px 24px;
  gap: 62px;
  align-items: stretch;
  max-width: 1440px;
  margin: 0 auto;
  width: 100%;
  box-sizing: border-box;
}

/* ---------- Left: 580px, design order = row(3) + title + row(3) ---------- */
.login-left {
  width: 580px;
  flex-shrink: 0;
  padding: 64px 0 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
  /* 从 src/views/ 到 src/assets：相对路径 ../assets/ */
  background-image: none;
  background-repeat: no-repeat;
  background-position: center center;
  background-size: cover;
  background-color: var(--color-sidebar);
}

/* 背景图四周渐变，与页面背景衔接 */
.login-left::before {
  content: "";
  position: absolute;
  inset: 0;
  background: radial-gradient(
    ellipse 80% 200% at 70% 50%,
    rgba(var(--color-brand-rgb), 0.24) 0%,
    var(--color-sidebar) 70%
  );
  pointer-events: none;
  z-index: 0;
}

.login-left::after {
  content: "";
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: 1;
  /* 四边向内的渐变，让背景图边缘柔和融入 var(--color-sidebar) */
  background:
    linear-gradient(
      90deg,
      var(--color-sidebar) 0%,
      transparent 20%,
      transparent 80%,
      var(--color-sidebar) 100%
    ),
    linear-gradient(
      0deg,
      var(--color-sidebar) 0%,
      transparent 18%,
      transparent 82%,
      var(--color-sidebar) 100%
    );
}

.left-content {
  position: relative;
  z-index: 2;
  width: 100%;
  max-width: 464px;
}

/* 左侧：preview.html 样式，标题 + SVG 曲线与节点 */
.left-content--preview {
  max-width: 420px;
  padding-bottom: 60px;
}

.journey-title-wrap {
  text-align: center;
  padding-top: 60px;
}

.journey-title-preview {
  font-size: 28px;
  font-weight: 700;
  color: #fff;
  margin: 0;
}

.journey-subtitle-preview {
  font-size: 28px;
  margin: 10px 0 0 0;
  background: linear-gradient(
    270deg,
    var(--color-accent-soft) 0%,
    var(--color-accent) 100%
  );
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.journey-svg {
  width: 100%;
  height: auto;
  margin-top: 40px;
  display: block;
}

.journey-path {
  stroke: rgba(255, 255, 255, 0.45);
  stroke-width: 2;
  fill: none;
}

.journey-node {
  fill: #0c0616;
  stroke: var(--color-accent);
  stroke-width: 2;
  filter: drop-shadow(0 0 8px rgba(185, 141, 63, 0.38));
}

.journey-node-glyph {
  fill: var(--color-accent-soft);
  font-family: "Font Awesome 6 Free";
  font-size: 22px;
  font-weight: 900;
  text-anchor: middle;
  pointer-events: none;
}

.journey-label {
  fill: #fff;
  font-size: 14px;
  text-anchor: middle;
  font-family: var(--font-ui);
}

/* Figma Frame 65: row of 3 items, each 154x62 (旧左侧，已由 preview 样式替代) */
.features-row {
  display: flex;
  gap: 0;
  justify-content: space-between;
  margin-bottom: 0;
}

.features-row-1 {
  margin-bottom: 32px;
}

.features-row-2 {
  margin-top: 32px;
}

.feature-item {
  width: 154px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.feature-icon {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: rgba(255, 255, 255, 0.85);
  margin-bottom: 8px;
}
.feature-icon-img {
  width: 28px;
  height: 28px;
  display: inline-block;
  vertical-align: middle;
}

.feature-text {
  font-size: 15px;
  line-height: 1.35;
  color: var(--color-accent-soft);
}

/* 左侧大标题：一行白字 + 一行渐变字，容器圆角深色底 */
.journey-title {
  font-size: 32px;
  font-weight: 700;
  line-height: 2;
  text-align: center;
  margin: 0;
  padding: 28px 36px;
  position: relative;
  border-radius: 84px;
  overflow: hidden;
}

.journey-title::before {
  content: "";
  position: absolute;
  inset: 0;
  background: var(--color-sidebar);
  border-radius: 84px;
  filter: blur(4px);
  -webkit-filter: blur(4px);
  z-index: -1;
}

.journey-title .line1,
.journey-title .line2 {
  display: block;
  position: relative;
  z-index: 1;
}

.journey-title .line1 {
  color: #fff;
}

.journey-title .line2 {
  background: linear-gradient(
    270deg,
    var(--color-accent-soft) 0%,
    var(--color-accent) 100%
  );
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  -webkit-text-fill-color: transparent;
}

/* 注册页左侧：大标题（UTrada 紫色）+ 5 步流程 + 虚线连接 */
.login-left--register .journey-title::before {
  background: var(--color-sidebar);
}
.journey-title--register {
  margin-bottom: 32px;
}
.journey-title--register .line2-register {
  background: none;
  -webkit-text-fill-color: inherit;
  color: #fff;
}
.journey-title--register .utrada-highlight {
  color: var(--color-accent);
  font-weight: 700;
}
/* 5 步一左一右交替 + 中间虚线连接 */
.journey-steps {
  display: flex;
  flex-direction: column;
  gap: 0;
  align-items: stretch;
  width: 100%;
  position: relative;
}
.journey-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  width: 100%;
}
.journey-step--left {
  align-items: flex-start;
}
.journey-step--right {
  align-items: flex-end;
}
.journey-step-icon {
  width: 48px;
  height: 48px;
  flex-shrink: 0;
  border: 1px solid rgba(142, 83, 255, 0.7);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 0 12px rgba(142, 83, 255, 0.25);
}
.journey-step-icon .feature-icon-img {
  width: 22px;
  height: 22px;
  filter: brightness(0) invert(1);
}
.journey-step-label {
  color: #fff;
  font-size: 16px;
  font-weight: 500;
}
.journey-step-connector {
  height: 32px;
  width: 100%;
  position: relative;
  flex-shrink: 0;
}
.journey-step-connector .connector-svg {
  width: 100%;
  height: 100%;
  display: block;
}
.journey-step-connector .connector-path {
  stroke: rgba(255, 255, 255, 0.3);
  stroke-width: 1px;
  vector-effect: non-scaling-stroke;
}

/* ---------- Right: 530px, inform top 136px (Figma Frame 75) ---------- */
.login-right {
  width: 530px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 64px 0 48px;
}

.form-panel {
  width: 100%;
  padding: 24px;
  box-sizing: border-box;
  background: #fff;
  border-radius: var(--radius-xl);
}

.form-title {
  color: #292929;
  margin: 0 0 24px 0;
  line-height: 1.2;
  text-align: center;
  font-family: "Work Sans";
  font-style: normal;
  font-weight: 600;
  font-size: 24px;
  line-height: 36px;
}

.form-title .title-line1,
.form-title .title-line2 {
  display: inline;
}

.form-title .title-line2 {
  margin-left: 0.25em;

  font-family: "Work Sans";
  font-style: normal;
  font-weight: 600;
  font-size: 24px;
  line-height: 36px;
  /* identical to box height, or 150% */
  text-align: center;

  /* UTrada- gradient/utra-color-primary-gradient */
  background: linear-gradient(
    90deg,
    var(--color-brand-strong) 0%,
    var(--color-brand) 100%
  );
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* Figma tab: 69px height, each tab 241x37 */
.tabs {
  display: flex;
  gap: 0;
  margin-bottom: 32px;
  border-bottom: 1px solid var(--color-border);
  height: 69px;
  align-items: center;
}

.tab {
  border: 0;
  background: transparent;
  width: 241px;
  height: 37px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  font-weight: 500;
  color: #dcdcdc;
  cursor: pointer;
  margin-bottom: -1px;
  border-bottom: 2px solid transparent;
  box-sizing: border-box;
}

.tab.active {
  color: var(--color-brand);
  background: var(--color-brand-soft);
  border-radius: 4px;
}

.tab:not(.active):hover {
  color: #dcdcdc;
}

.login-form {
  display: flex;
  flex-direction: column;
}

.form-group {
  margin-bottom: 24px;
}

.form-group label {
  display: block;
  font-size: 16px;
  font-weight: 500;
  color: #656565;
  margin-bottom: 8px;
}

.label-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.label-row label {
  margin-bottom: 0;
}

.forgot-link {
  font-size: 14px;
  color: var(--color-brand);
  text-decoration: none;
  font-weight: 500;
}

.forgot-link:hover {
  text-decoration: underline;
}

.form-group input,
.form-group select {
  width: 100%;
  height: 37px;
  padding: 8px 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 15px;
  transition:
    border-color 0.2s,
    box-shadow 0.2s;
  background: #fff;
  box-sizing: border-box;
}
.form-group input {
  font-style: normal;
  font-weight: 500;
  font-size: 16px;
  line-height: 21px;
  color: #292929;
}

.form-group input::placeholder {
  color: var(--color-faint);
}

/* 注册：手机区号 + 手机号 单独布局，区号固定宽度，号码输入框占满剩余空间 */
.phone-input-row {
  display: flex;
  flex-wrap: nowrap;
  gap: 10px;
  align-items: center;
  width: 100%;
}

.form-group .phone-code-select,
.phone-input-row .phone-code-select {
  width: 88px;
  min-width: 88px;
  max-width: 88px;
  flex-shrink: 0;
  box-sizing: border-box;
}

.form-group .phone-code-select :deep(.custom-select__trigger),
.phone-input-row .phone-code-select :deep(.custom-select__trigger) {
  min-height: 37px;
  height: 37px;
  padding: 8px 10px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: #fff;
  color: #292929;
  font-size: 14px;
  box-sizing: border-box;
  box-shadow: none;
}

.form-group .phone-code-select :deep(.custom-select__trigger.placeholder),
.phone-input-row .phone-code-select :deep(.custom-select__trigger.placeholder) {
  color: var(--color-faint);
}

.form-group .phone-code-select :deep(.custom-select__trigger.open),
.form-group .phone-code-select :deep(.custom-select__trigger:focus),
.phone-input-row .phone-code-select :deep(.custom-select__trigger.open),
.phone-input-row .phone-code-select :deep(.custom-select__trigger:focus) {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 2px var(--color-focus-ring);
}

.form-group .phone-code-select :deep(.custom-select__trigger.error),
.phone-input-row .phone-code-select :deep(.custom-select__trigger.error) {
  border-color: var(--color-danger);
  box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.15);
}

.form-group .phone-code-select :deep(.custom-select__trigger-text),
.phone-input-row .phone-code-select :deep(.custom-select__trigger-text) {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.phone-input-row input {
  flex: 1 1 0;
  min-width: 0;
  width: 0;
  height: 37px;
  padding: 8px 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 15px;
  box-sizing: border-box;
}

.form-group.error .phone-code-select,
.form-group.error .phone-input-row input {
  border-color: var(--color-danger);
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 2px var(--color-focus-ring);
}

.form-group.error input,
.form-group.error select {
  border-color: var(--color-danger);
}

.form-group.error input:focus {
  border-color: var(--color-danger);
  box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.15);
}

.register-country-select {
  width: 100%;
}

.register-country-select :deep(.custom-select__trigger) {
  min-height: 37px;
  height: 37px;
  padding: 8px 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: #fff;
  color: #292929;
  font-size: 15px;
  box-shadow: none;
}

.register-country-select :deep(.custom-select__trigger.placeholder) {
  color: var(--color-faint);
}

.register-country-select :deep(.custom-select__trigger.open),
.register-country-select :deep(.custom-select__trigger:focus) {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 2px var(--color-focus-ring);
}

.register-country-select :deep(.custom-select__trigger.error) {
  border-color: var(--color-danger);
  box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.15);
}

.register-country-select :deep(.custom-select__trigger-text) {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.register-country-select :deep(.custom-select__menu) {
  border-color: var(--color-border);
  border-radius: var(--radius-md);
}

.field-error {
  display: block;
  margin-top: 6px;
  font-size: 13px;
  color: var(--color-danger);
}

.password-input-wrapper {
  position: relative;
  width: 100%;
}

.password-input-wrapper input {
  padding-right: 44px;
}

.password-toggle {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  padding: 6px;
  color: var(--color-muted);
  font-size: 16px;
}

.password-toggle:hover {
  color: var(--color-brand);
}

.password-hint {
  display: block;
  margin-top: 6px;
  font-size: 13px;
  color: var(--color-muted);
}

.remember-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 32px;
}

.remember-row input {
  width: 16px;
  height: 16px;
  cursor: pointer;
}

.remember-row label {
  font-size: 15px;
  font-weight: 400;
  color: var(--color-text);
  cursor: pointer;
  margin: 0;
}

.btn-primary {
  width: 100%;
  height: 48px;
  background: linear-gradient(
    90deg,
    var(--color-brand) 0%,
    var(--color-accent) 100%
  );
  border: 1.5px solid var(--color-brand);
  border-radius: 32px;
  color: #fff;
  border: none;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition:
    background 0.2s,
    transform 0.1s;
  margin-top: 8px;
}

.btn-primary:hover:not(:disabled) {
  background: linear-gradient(
    90deg,
    var(--color-brand) 0%,
    var(--color-accent) 100%
  );
  border: 1.5px solid var(--color-brand);
  border-radius: 32px;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-continue {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn-continue-icon {
  width: auto;
  height: auto;
  font-size: 13px;
  flex-shrink: 0;
}

.general-error {
  margin-top: 16px;
  padding: 10px 14px;
  background: var(--color-danger-soft);
  border: 1px solid var(--color-danger-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  color: var(--color-danger);
}

/* Forgot Password 右侧视图（与登录/注册同风格） */
.forgot-password-view .form-title {
  margin-bottom: 8px;
}

.form-subtitle {
  text-align: center;
  color: var(--color-muted);
  font-size: 15px;
  margin-bottom: 24px;
  line-height: 1.4;
}

.forgot-success {
  text-align: center;
  padding: 16px 0;
}

.forgot-success-hint {
  display: block;
  color: var(--color-brand);
  font-size: 14px;
  margin-top: 10px;
  margin-bottom: 0;
  line-height: 1.5;
}

.forgot-password-view input[readonly] {
  background: #f5f5f5;
  cursor: not-allowed;
  color: var(--color-text);
}

.back-to-signin {
  font-size: 14px;
  font-weight: 500;
  color: #292929;
  text-decoration: none;
}

.back-to-signin:hover {
  text-decoration: underline;
}

.back-row {
  text-align: center;
  margin-top: 24px;
}

.success-message {
  margin-top: 16px;
  padding: 14px 16px;
  background: var(--color-success-soft);
  border: 1px solid var(--color-success-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  color: var(--color-success);
}

.register-form .terms-checkbox-wrap {
  margin-bottom: 24px;
}

.register-form .terms-checkbox {
  margin-bottom: 0;
}

.register-form .terms-checkbox-block {
  display: block;
}

.register-form .terms-label {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  cursor: pointer;
  font-size: 14px;
  color: var(--color-text);
}

.register-form .terms-label input {
  width: 16px;
  height: 16px;
  margin-top: 2px;
  flex-shrink: 0;
}

.register-form .terms-label a {
  color: var(--color-brand);
  text-decoration: underline;
}

/* 条款错误信息：在整个条款区域下方单独一行显示，不挤在文字内 */
.register-form .terms-field-error {
  display: block;
  margin-top: 10px;
  margin-left: 0;
}

.form-hint {
  font-size: 14px;
  color: var(--color-muted);
  margin-bottom: 24px;
}

/* Modal Styles */
.modal {
  display: grid;
  place-items: center;
  position: fixed;
  z-index: 1000;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.5);
  animation: fadeIn 0.3s ease;
  padding: 20px;
}

.modal-content {
  background-color: #ffffff;
  margin: auto;
  padding: 0;
  border-radius: 20px;
  max-width: 500px;
  width: 90%;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  animation: slideDown 0.3s ease;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  background: var(--color-brand);
  color: white;
  padding: 30px;
  border-radius: 20px 20px 0 0;
  position: relative;
}

.modal-header h2 {
  margin: 0;
  font-size: 28px;
}

.modal-header p {
  margin: 10px 0 0 0;
  opacity: 0.9;
  font-size: 15px;
}

.close {
  border: 0;
  background: transparent;
  position: absolute;
  right: 25px;
  top: 25px;
  color: white;
  font-size: 32px;
  font-weight: 300;
  cursor: pointer;
  transition: transform 0.2s ease;
  line-height: 1;
}

.close:hover {
  transform: rotate(90deg);
}

.modal-body {
  padding: 40px;
}

.modal-form-group {
  margin-bottom: 25px;
}

.modal-form-group label {
  display: block;
  margin-bottom: 8px;
  color: var(--color-text);
  font-weight: 500;
  font-size: 14px;
}

.modal-form-group input,
.modal-form-group select {
  width: 100%;
  padding: 14px 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 15px;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
  background: white;
}

.modal-form-group input:focus,
.modal-form-group select:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 2px var(--color-focus-ring);
}

.modal-custom-select {
  position: relative;
}

.modal-custom-select-trigger {
  width: 100%;
  min-height: 50px;
  padding: 14px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: #fff;
  color: var(--color-ink);
  font-size: 15px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  cursor: pointer;
  transition:
    border-color 0.3s ease,
    box-shadow 0.3s ease;
}

.modal-custom-select-trigger.placeholder {
  color: #94a3b8;
}

.modal-custom-select-trigger.open,
.modal-custom-select-trigger:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.modal-custom-select-trigger i {
  font-size: 13px;
  color: #64748b;
  flex-shrink: 0;
}

.modal-custom-select-menu {
  position: absolute;
  left: 0;
  right: 0;
  bottom: calc(100% + 8px);
  z-index: 20;
  background: #fff;
  border: 1px solid #d8deea;
  border-radius: var(--radius-lg);
  box-shadow: 0 18px 35px rgba(15, 23, 42, 0.12);
  overflow: hidden;
}

.modal-custom-select-search {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-bottom: 1px solid var(--color-border);
  background: var(--color-surface-soft);
}

.modal-custom-select-search i {
  color: #94a3b8;
}

.modal-custom-select-search input {
  border: 0;
  padding: 0;
  box-shadow: none;
  background: transparent;
}

.modal-custom-select-search input:focus {
  box-shadow: none;
}

.modal-custom-select-options {
  max-height: 220px;
  overflow-y: auto;
}

.modal-custom-select-option {
  width: 100%;
  border: 0;
  background: #fff;
  padding: 11px 14px;
  text-align: left;
  font-size: 14px;
  color: var(--color-ink);
  cursor: pointer;
}

.modal-custom-select-option:hover,
.modal-custom-select-option.selected {
  background: var(--color-brand-soft);
  color: var(--color-brand-strong);
}

.modal-custom-select-empty {
  padding: 14px;
  font-size: 13px;
  color: var(--color-muted);
}

/* Password input wrapper in modal */
.modal-form-group .password-input-wrapper {
  position: relative;
  width: 100%;
}

.modal-form-group .password-input-wrapper input {
  width: 100%;
  padding-right: 50px;
}

.modal-form-group small {
  display: block;
  margin-top: 5px;
  color: var(--color-muted);
  font-size: 13px;
}

/* Password requirement hint */
.password-requirement-hint {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 8px;
  padding: 10px 12px;
  background: var(--color-brand-soft);
  border-left: 3px solid var(--color-brand);
  border-radius: var(--radius-sm);
  color: var(--color-text);
  font-size: 13px;
  line-height: 1.5;
}

.password-requirement-hint i {
  color: var(--color-brand);
  font-size: 14px;
  flex-shrink: 0;
}

.modal-button {
  width: 100%;
  padding: 16px;
  background: var(--color-brand);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition:
    background 0.2s,
    transform 0.1s;
}

.modal-button:hover:not(:disabled) {
  background: var(--color-brand-strong);
}

.modal-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.terms {
  margin-top: 20px;
  font-size: 13px;
  color: var(--color-muted);
  text-align: center;
}

.terms a {
  color: var(--color-brand);
  text-decoration: none;
}

.terms-checkbox {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 25px;
  padding: 18px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  border: 2px solid var(--color-border);
}

.terms-checkbox input {
  width: 20px;
  height: 20px;
  cursor: pointer;
  margin-top: 2px;
  flex-shrink: 0;
}

.terms-checkbox label {
  color: var(--color-text);
  font-size: 14px;
  line-height: 1.6;
  cursor: pointer;
  margin: 0;
}

.success-message {
  background: var(--color-success-soft);
  color: #155724;
  padding: 15px;
  border-radius: var(--radius-md);
  margin-bottom: 20px;
  border: 1px solid #c3e6cb;
  text-align: center;
}

.error-message {
  background: var(--color-danger-soft);
  color: var(--color-danger);
  padding: 12px 15px;
  border-radius: var(--radius-md);
  margin-bottom: 15px;
  border: 1px solid var(--color-danger-border);
  font-size: 14px;
  line-height: 1.5;
}

.error-notification {
  position: fixed;
  top: 20px;
  right: 20px;
  background: var(--color-danger-soft);
  color: var(--color-danger);
  padding: 14px 40px 14px 20px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-danger-border);
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
  z-index: 2000;
  font-size: 14px;
}

.error-notification .close-btn {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  font-size: 18px;
  color: var(--color-danger);
  cursor: pointer;
}

@keyframes slideIn {
  from {
    transform: translateX(400px);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.required {
  color: var(--color-danger);
}

/* Legal Document Modal：无顶栏、右上角 ×、正文标题/正文/底部按钮样式 */
.legal-modal .modal-content {
  max-width: 800px;
  max-height: 85vh;
  position: relative;
}

.legal-content {
  display: flex;
  flex-direction: column;
  max-height: 85vh;
}

.legal-modal-close {
  position: absolute;
  top: 16px;
  right: 16px;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  line-height: 1;
  color: #656565;
  cursor: pointer;
  z-index: 1;
  border: none;
  background: transparent;
}

.legal-modal-close:hover {
  color: #292929;
}

.legal-body {
  flex: 1;
  overflow-y: auto;
  padding: 48px 40px 30px;
  max-height: calc(85vh - 80px);
}

.legal-doc-title {
  font-family: "Work Sans", sans-serif;
  font-style: normal;
  font-weight: 600;
  font-size: 24px;
  line-height: 36px;
  text-align: center;
  color: #292929;
  margin: 0 0 20px 0;
}

.legal-document-content {
  font-family: "Work Sans", sans-serif;
  font-style: normal;
  font-weight: 400;
  font-size: 10px;
  line-height: 15px;
  color: #989898;
}

.legal-document-content :deep(h1),
.legal-document-content :deep(h2),
.legal-document-content :deep(h3) {
  font-family: "Work Sans", sans-serif;
  font-weight: 600;
  font-size: 24px;
  line-height: 36px;
  color: #292929;
  margin-bottom: 12px;
  margin-top: 16px;
}

.legal-document-content :deep(p) {
  margin-bottom: 12px;
  text-align: justify;
}

.legal-document-content :deep(ul),
.legal-document-content :deep(ol) {
  margin-left: 20px;
  margin-bottom: 12px;
}

.legal-document-content :deep(li) {
  margin-bottom: 6px;
}

.legal-document-content :deep(strong) {
  color: #989898;
  font-weight: 600;
}

.legal-document-content :deep(a) {
  color: var(--color-brand);
  text-decoration: underline;
}

.legal-document-content :deep(a):hover {
  color: var(--color-brand-strong);
}

.legal-footer {
  margin-top: 24px;
  padding-top: 0;
  border-top: none;
  text-align: right;
}

.legal-footer-btn {
  font-family: "Work Sans", sans-serif;
  font-style: normal;
  font-weight: 500;
  font-size: 12px;
  line-height: 130%;
  text-align: center;
  color: #ffffff;
  background: linear-gradient(
    90deg,
    var(--color-brand-strong) 0%,
    var(--color-brand) 100%
  );
  border: 1.5px solid var(--color-brand);
  border-radius: 20px;
  padding: 10px 24px;
  cursor: pointer;
  min-height: 40px;
}

.legal-footer-btn:hover {
  opacity: 0.95;
}

.terms-checkbox a {
  color: var(--color-brand);
  text-decoration: underline;
  font-weight: 600;
  transition: color 0.2s ease;
}

.terms-checkbox a:hover {
  color: var(--color-brand-strong);
}

@media (max-width: 1200px) {
  .login-header {
    padding: 0 24px;
  }
  .language-dropdown {
    right: 24px;
  }
  .login-body {
    padding: 0 24px;
    gap: 32px;
  }
  .login-left {
    width: 100%;
    max-width: 400px;
  }
  .left-content {
    max-width: 100%;
  }
  .feature-item {
    width: 120px;
  }
  .feature-text {
    font-size: 13px;
  }
  .journey-title {
    font-size: 36px;
  }
  .login-right {
    width: 100%;
    max-width: 530px;
  }
}

@media (max-width: 768px) {
  .login-body {
    flex-direction: column;
    padding: 0 16px;
    gap: 0;
  }
  .login-left {
    width: 100%;
    max-width: none;
    min-height: 280px;
    padding: 32px 0 24px;
  }
  .features-row {
    flex-wrap: wrap;
    justify-content: center;
    gap: 16px;
  }
  .features-row-1 {
    margin-bottom: 20px;
  }
  .features-row-2 {
    margin-top: 20px;
  }
  .feature-item {
    width: 140px;
  }
  .journey-title {
    font-size: 28px;
    margin-bottom: 0;
  }
  .login-right {
    width: 100%;
    max-width: none;
  }
  .form-panel {
    padding: 32px 16px 40px;
  }
  .form-title {
    font-size: 28px;
  }
  .tabs {
    height: auto;
    margin-bottom: 24px;
  }
  .tab {
    width: 50%;
    min-width: 0;
    padding: 12px 8px;
    font-size: 16px;
  }
  .phone-input-row {
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
  }
  .form-group .phone-code-select,
  .phone-input-row .phone-code-select,
  .phone-input-row input {
    width: 100%;
    min-width: 0;
    max-width: none;
  }
  .phone-input-row input {
    flex: none;
  }
}

/* Quiet-luxury portal composition */
.utrada-login-page {
  background: var(--color-canvas);
  color: var(--color-text);
}

.login-header {
  height: 76px;
  padding: 0 clamp(24px, 5vw, 72px);
  background: var(--color-sidebar);
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.header-logo-img {
  height: 30px;
}

.language-switcher {
  min-height: 38px;
  padding: 8px 11px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: var(--radius-md);
}

.language-dropdown {
  top: 66px;
  right: clamp(24px, 5vw, 72px);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
}

.language-option.active {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.login-body {
  min-height: calc(100vh - 76px);
  max-width: 1480px;
  padding: 28px clamp(24px, 4vw, 64px) 48px;
  gap: 28px;
  align-items: stretch;
}

.login-left {
  width: auto;
  flex: 1 1 0;
  min-width: 0;
  padding: 48px;
  background-color: var(--color-sidebar);
  background-image: none;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 24px;
  box-shadow: var(--shadow-md);
}

.login-left::before {
  background:
    radial-gradient(
      circle at 22% 18%,
      rgba(185, 141, 63, 0.24),
      transparent 26%
    ),
    radial-gradient(
      circle at 78% 72%,
      rgba(var(--color-brand-rgb), 0.7),
      transparent 36%
    );
}

.login-left::after {
  background:
    linear-gradient(
      120deg,
      transparent 0 48%,
      rgba(255, 255, 255, 0.035) 48% 49%,
      transparent 49% 100%
    ),
    linear-gradient(
      25deg,
      transparent 0 62%,
      rgba(216, 188, 131, 0.1) 62% 63%,
      transparent 63% 100%
    );
}

.left-content {
  max-width: 560px;
}

.features-row {
  gap: 18px;
}

.feature-item {
  width: auto;
  flex: 1 1 0;
  align-items: flex-start;
  text-align: left;
}

.feature-icon {
  color: var(--color-accent-soft);
}

.feature-icon-img {
  opacity: 0.82;
  filter: grayscale(1) sepia(1) saturate(1.2) hue-rotate(350deg)
    brightness(1.18);
}

.feature-text {
  color: rgba(255, 255, 255, 0.7);
  font-size: 13px;
}

.journey-title {
  padding: 42px 0;
  border-radius: 0;
  font-family: var(--font-display);
  font-size: clamp(38px, 4.2vw, 64px);
  font-weight: 500;
  line-height: 1.05;
  letter-spacing: -0.04em;
  text-align: left;
}

.journey-title::before {
  display: none;
}

.journey-title .line2,
.journey-subtitle-preview {
  margin-top: 8px;
  background: none;
  color: #d8bc83;
  -webkit-text-fill-color: currentColor;
}

.journey-title-wrap {
  text-align: left;
}

.journey-title-preview,
.journey-subtitle-preview {
  font-family: var(--font-display);
  font-size: 38px;
  font-weight: 500;
}

.journey-node {
  fill: var(--color-sidebar-raised);
  stroke: var(--color-accent);
  filter: drop-shadow(0 0 8px rgba(185, 141, 63, 0.38));
}

.login-right {
  width: 520px;
  padding: 0;
  justify-content: center;
}

.form-panel {
  padding: 42px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-lg);
}

.form-title {
  margin-bottom: 28px;
  font-family: var(--font-display);
  font-size: 32px;
  line-height: 1.15;
  font-weight: 600;
  letter-spacing: -0.025em;
  color: var(--color-ink);
}

.form-title .title-line2 {
  font-family: inherit;
  font-size: inherit;
  line-height: inherit;
  background: none;
  color: var(--color-brand);
  -webkit-text-fill-color: currentColor;
}

.tabs {
  height: 48px;
  padding: 4px;
  gap: 4px;
  margin-bottom: 28px;
  background: var(--color-surface-soft);
  border: 0;
  border-radius: var(--radius-md);
}

.tab {
  flex: 1;
  width: auto;
  height: 40px;
  margin: 0;
  border: 0;
  border-radius: var(--radius-sm);
  color: var(--color-muted);
  font-size: 15px;
}

.tab.active {
  color: var(--color-brand);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.tab:not(.active):hover {
  color: var(--color-ink);
}

.form-group input,
.form-group select,
.register-country-select :deep(.custom-select__trigger),
.form-group .phone-code-select :deep(.custom-select__trigger) {
  min-height: 48px;
  border-width: 1px;
  border-color: var(--color-border);
  border-radius: var(--radius-md);
}

.btn-primary,
.modal-button,
.legal-footer-btn {
  background: var(--color-brand);
  border: 1px solid var(--color-brand);
  border-radius: var(--radius-md);
  box-shadow: none;
}

.btn-primary:hover:not(:disabled),
.modal-button:hover:not(:disabled),
.legal-footer-btn:hover {
  background: var(--color-brand-strong);
  border-color: var(--color-brand-strong);
  transform: translateY(-1px);
  box-shadow: var(--shadow-sm);
}

.modal-content {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-lg);
}

.modal-header {
  background: var(--color-brand);
}

@media (max-width: 1100px) {
  .login-body {
    padding: 24px;
    gap: 20px;
  }

  .login-left {
    max-width: none;
    padding: 36px;
  }

  .login-right {
    width: min(50%, 520px);
  }

  .form-panel {
    padding: 34px;
  }
}

@media (max-width: 768px) {
  .login-header {
    height: 68px;
    padding: 0 16px;
  }

  .language-dropdown {
    top: 60px;
    right: 16px;
  }

  .login-body {
    min-height: calc(100vh - 68px);
    padding: 16px;
  }

  .login-left {
    display: none;
  }

  .login-right {
    width: 100%;
    padding: 0;
  }

  .form-panel {
    padding: 28px 22px;
    border-radius: var(--radius-lg);
  }

  .form-title {
    font-size: 28px;
  }
}
</style>
