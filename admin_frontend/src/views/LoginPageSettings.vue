<template>
  <div class="container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-title">
        <h1>{{ t("page_loginPageSettings_title") }}</h1>
        <p>{{ t("page_loginPageSettings_sub") }}</p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- Login Page Branding Settings -->
    <div class="settings-card" :class="{ collapsed: collapsedCards.branding }">
      <div class="card-header" @click="toggleCard('branding')">
        <div class="card-header-content">
          <h2>
            <i class="fas fa-paint-brush"></i>
            {{ t("lps_card_branding_title") }}
          </h2>
          <p>{{ t("lps_card_branding_desc") }}</p>
        </div>
        <i class="fas fa-chevron-down card-collapse-icon"></i>
      </div>
      <div class="card-body">
        <!-- Logo Type -->
        <div class="form-group">
          <label>
            {{ t("lps_label_companyLogo") }}
            <span class="label-description">{{
              t("lps_label_companyLogo_hint")
            }}</span>
          </label>

          <div class="logo-type-selector">
            <label>
              <input
                type="radio"
                v-model="branding.logoType"
                value="text"
                :disabled="!hasEditPermission"
              />
              <span>{{ t("lps_logoType_text") }}</span>
            </label>
            <label>
              <input
                type="radio"
                v-model="branding.logoType"
                value="image"
                :disabled="!hasEditPermission"
              />
              <span>{{ t("lps_logoType_image") }}</span>
            </label>
          </div>

          <!-- Text Logo -->
          <div v-if="branding.logoType === 'text'" class="logo-text-input">
            <input
              type="text"
              v-model="branding.logoText"
              :placeholder="t('lps_ph_companyName')"
              :disabled="!hasEditPermission"
            />
          </div>

          <!-- Image Logo -->
          <div v-if="branding.logoType === 'image'" class="logo-image-upload">
            <div
              v-if="hasEditPermission"
              class="file-upload-area"
              @click="$refs.logoFileInput.click()"
            >
              <div class="upload-icon">
                <i class="fas fa-cloud-upload-alt"></i>
              </div>
              <p>
                <strong>{{ t("lps_upload_logo_title") }}</strong>
              </p>
              <p class="file-info">{{ t("lps_upload_logo_formats") }}</p>
            </div>
            <input
              ref="logoFileInput"
              type="file"
              accept="image/*"
              @change="handleLogoUpload"
              style="display: none"
            />
            <div v-if="branding.logoImagePath" class="uploaded-file">
              <div class="file-details">
                <i class="fas fa-image file-icon"></i>
                <div>
                  <div class="file-name">{{ branding.logoImagePath }}</div>
                </div>
              </div>
              <button
                v-if="hasEditPermission"
                type="button"
                class="remove-file"
                @click="removeLogo"
              >
                <i class="fas fa-times"></i> {{ t("lps_btn_remove") }}
              </button>
            </div>
          </div>
        </div>

        <!-- Tagline -->
        <div class="form-group">
          <label>
            {{ t("lps_label_tagline") }}
            <span class="label-description">{{
              t("lps_label_tagline_hint")
            }}</span>
          </label>
          <input
            type="text"
            v-model="branding.taglineEn"
            :placeholder="t('lps_ph_tagline')"
            :disabled="!hasEditPermission"
          />
        </div>

        <!-- Features Content -->
        <div class="form-group">
          <label>
            {{ t("lps_label_features") }}
            <span class="label-description">{{
              t("lps_label_features_hint")
            }}</span>
          </label>
          <RichTextInput
            v-model="branding.featuresContent"
            :disabled="!hasEditPermission"
            :placeholder="t('lps_ph_features')"
          />
        </div>

        <div class="info-box">
          <p>
            <strong
              ><i class="fas fa-info-circle"></i>
              {{ t("lps_word_note") }}:</strong
            >
            {{ t("lps_note_branding") }}
          </p>
        </div>

        <div class="card-save-section">
          <button
            v-if="hasEditPermission"
            class="btn btn-primary"
            @click="saveBranding"
            :disabled="savingBranding"
          >
            <i class="fas fa-save"></i>
            {{ savingBranding ? t("lps_saving") : t("lps_btn_saveChanges") }}
          </button>
        </div>
      </div>
    </div>

    <!-- Registration Form Fields Settings -->
    <div
      class="settings-card"
      :class="{ collapsed: collapsedCards.registration }"
    >
      <div class="card-header" @click="toggleCard('registration')">
        <div class="card-header-content">
          <h2>
            <i class="fas fa-user-plus"></i>
            {{ t("lps_card_registration_title") }}
          </h2>
          <p>{{ t("lps_card_registration_desc") }}</p>
        </div>
        <i class="fas fa-chevron-down card-collapse-icon"></i>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label>
            {{ t("lps_label_formFields") }}
            <span class="label-description">{{
              t("lps_label_formFields_hint")
            }}</span>
          </label>

          <div class="registration-fields-list">
            <div
              v-for="(field, index) in formFields"
              :key="field.id || field.fieldId"
              class="registration-field-item"
              :class="{
                'field-disabled': !field.isEnabled,
                'field-mandatory': field.isMandatory,
                dragging: draggedFieldIndex === index,
                'drag-over': dragOverIndex === index,
              }"
              draggable="true"
              @dragstart="onFieldDragStart($event, index)"
              @dragend="onFieldDragEnd"
              @dragover.prevent="onFieldDragOver($event, index)"
              @dragleave="onFieldDragLeave"
              @drop="onFieldDrop($event, index)"
            >
              <!-- Drag Handle -->
              <div class="drag-handle">
                <i class="fas fa-grip-vertical"></i>
              </div>

              <div class="field-left">
                <label
                  class="toggle-switch"
                  :class="{ disabled: !hasEditPermission }"
                >
                  <input
                    type="checkbox"
                    v-model="field.isEnabled"
                    :disabled="field.isMandatory || !hasEditPermission"
                    class="field-enabled"
                  />
                  <span class="toggle-slider"></span>
                </label>
                <div class="field-content">
                  <div class="field-name-row">
                    <input
                      type="text"
                      v-model="field.fieldName"
                      :disabled="field.isMandatory || !hasEditPermission"
                      class="field-name-input"
                    />
                    <span v-if="field.isMandatory" class="mandatory-badge">{{
                      t("lps_mandatory_badge")
                    }}</span>
                  </div>
                  <input
                    type="text"
                    v-model="field.fieldDescription"
                    :disabled="field.isMandatory || !hasEditPermission"
                    class="field-description-input"
                    :placeholder="t('lps_ph_fieldDesc')"
                  />
                </div>
              </div>
              <div class="field-right">
                <label
                  class="required-checkbox"
                  :class="{ disabled: !hasEditPermission }"
                >
                  <input
                    type="checkbox"
                    v-model="field.isRequired"
                    :disabled="field.isMandatory || !hasEditPermission"
                    class="field-required"
                  />
                  <span>{{ t("lps_field_required") }}</span>
                </label>
                <button
                  v-if="!field.isMandatory && hasEditPermission"
                  type="button"
                  class="btn-small btn-delete"
                  @click="deleteField(field.id)"
                >
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
          </div>

          <button
            v-if="hasEditPermission"
            type="button"
            class="btn-small btn-add"
            @click="addNewField"
          >
            <i class="fas fa-plus"></i> {{ t("lps_btn_addField") }}
          </button>
        </div>

        <!-- Password Strength -->
        <div class="form-group password-strength-section">
          <label>
            <i
              class="fas fa-lock"
              style="color: var(--color-brand); margin-right: 8px"
            ></i
            >{{ t("lps_label_passwordStrength") }}
            <span class="label-description">{{
              t("lps_label_passwordStrength_hint")
            }}</span>
          </label>

          <div class="password-strength-options">
            <label
              v-for="level in passwordLevels"
              :key="level.value"
              class="strength-option"
              :class="{
                selected: passwordStrength.strengthLevel === level.value,
              }"
              @click="hasEditPermission && selectPasswordLevel(level.value)"
            >
              <input
                type="radio"
                name="password-strength"
                v-model="passwordStrength.strengthLevel"
                :value="level.value"
                :disabled="!hasEditPermission"
              />
              <div class="strength-title">
                <span>{{ level.name }}</span>
                <span class="strength-badge" :class="level.value">{{
                  level.badge
                }}</span>
              </div>
              <div class="strength-description">{{ level.description }}</div>
              <div
                class="strength-requirements font-floor-content"
                v-html="level.requirements"
              ></div>
            </label>
          </div>
        </div>

        <div class="info-box">
          <p>
            <strong
              ><i class="fas fa-info-circle"></i>
              {{ t("lps_word_note") }}:</strong
            >
            {{ t("lps_note_registration") }}
          </p>
        </div>

        <div
          class="info-box"
          style="
            background: var(--color-success-soft);
            border-left-color: #38b2ac;
          "
        >
          <p>
            <strong
              ><i class="fas fa-arrows-alt"></i>
              {{ t("lps_word_dragDropTitle") }}:</strong
            >
            {{ t("lps_note_dragDrop") }}
          </p>
        </div>

        <div class="info-box info-box-warning">
          <p>
            <strong
              ><i class="fas fa-exclamation-triangle"></i>
              {{ t("lps_word_important") }}:</strong
            >
            {{ t("lps_note_fieldsImportant") }}
          </p>
        </div>

        <div class="card-save-section">
          <button
            v-if="hasEditPermission"
            class="btn btn-primary"
            @click="saveRegistrationSettings"
            :disabled="savingRegistration"
          >
            <i class="fas fa-save"></i>
            {{
              savingRegistration ? t("lps_saving") : t("lps_btn_saveChanges")
            }}
          </button>
        </div>
      </div>
    </div>

    <!-- Country Settings -->
    <div class="settings-card" :class="{ collapsed: collapsedCards.countries }">
      <div class="card-header" @click="toggleCard('countries')">
        <div class="card-header-content">
          <h2>
            <i class="fas fa-globe"></i> {{ t("lps_card_countries_title") }}
          </h2>
          <p>{{ t("lps_card_countries_desc") }}</p>
        </div>
        <i class="fas fa-chevron-down card-collapse-icon"></i>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label>
            {{ t("lps_label_countryList") }}
            <span class="label-description">{{
              t("lps_label_countryList_hint")
            }}</span>
          </label>

          <div class="country-toolbar">
            <div class="country-search">
              <input
                type="text"
                v-model="countrySearch"
                :placeholder="t('lps_country_search_ph')"
                :disabled="!hasEditPermission && !hasReadonlyPermission"
              />
            </div>
            <div class="country-filter">
              <select
                v-model="countryFilter"
                :disabled="!hasEditPermission && !hasReadonlyPermission"
              >
                <option value="all">{{ t("lps_country_filter_all") }}</option>
                <option value="active">
                  {{ t("lps_country_filter_enabled") }}
                </option>
                <option value="inactive">
                  {{ t("lps_country_filter_disabled") }}
                </option>
              </select>
            </div>
            <button
              type="button"
              class="btn-small btn-secondary btn-country-toggle"
              @click="toggleSelectAllFiltered"
              :disabled="!hasEditPermission || filteredCountries.length === 0"
            >
              <i
                :class="
                  allFilteredSelected ? 'fas fa-square' : 'fas fa-check-square'
                "
              ></i>
              {{
                allFilteredSelected
                  ? t("lps_country_deselectAll")
                  : t("lps_country_selectAll")
              }}
            </button>
            <span class="country-count">{{
              tParams("lps_country_items", "", {
                count: filteredCountries.length,
              })
            }}</span>
          </div>

          <div class="countries-table">
            <div class="country-row country-header">
              <div class="country-col code">{{ t("lps_country_th_code") }}</div>
              <div class="country-col name">{{ t("lps_country_th_name") }}</div>
              <div class="country-col phone">
                {{ t("lps_country_th_phone") }}
              </div>
              <div class="country-col status">
                {{ t("lps_country_th_enabled") }}
              </div>
            </div>
            <div
              v-for="country in filteredCountries"
              :key="country.id"
              class="country-row"
            >
              <div class="country-col code">{{ country.code }}</div>
              <div class="country-col name">
                <span
                  class="country-flag fi"
                  :class="`fi-${getFlagCode(country.code)}`"
                ></span>
                <span>{{ country.name }}</span>
              </div>
              <div class="country-col phone">
                {{ country.phoneCode || "-" }}
              </div>
              <div class="country-col status">
                <label
                  class="toggle-switch"
                  :class="{ disabled: !hasEditPermission }"
                >
                  <input
                    type="checkbox"
                    v-model="country.isActive"
                    :disabled="!hasEditPermission"
                  />
                  <span class="toggle-slider"></span>
                </label>
              </div>
            </div>
            <div v-if="filteredCountries.length === 0" class="empty-state">
              <i class="fas fa-inbox"></i>
              <p>{{ t("lps_country_empty") }}</p>
            </div>
          </div>

          <div class="card-save-section">
            <button
              v-if="hasEditPermission"
              class="btn btn-primary"
              @click="saveCountries"
              :disabled="savingCountries"
            >
              <i class="fas fa-save"></i>
              {{ savingCountries ? t("lps_saving") : t("lps_btn_saveChanges") }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Legal Documents Management -->
    <div class="settings-card" :class="{ collapsed: collapsedCards.legal }">
      <div class="card-header" @click="toggleCard('legal')">
        <div class="card-header-content">
          <h2>
            <i class="fas fa-file-alt"></i> {{ t("lps_card_legal_title") }}
          </h2>
          <p>{{ t("lps_card_legal_desc") }}</p>
        </div>
        <i class="fas fa-chevron-down card-collapse-icon"></i>
      </div>
      <div class="card-body">
        <div class="legal-documents-list">
          <div
            v-for="doc in legalDocuments"
            :key="doc.id"
            class="legal-document-item"
          >
            <div class="document-header">
              <input
                type="text"
                v-model="doc.title"
                :placeholder="t('lps_ph_documentTitle')"
                class="document-title"
                :disabled="doc.isMandatory || !hasEditPermission"
              />
              <span v-if="doc.isMandatory" class="mandatory-badge">{{
                t("lps_mandatory_badge")
              }}</span>
              <button
                v-if="!doc.isMandatory && hasEditPermission"
                type="button"
                class="btn-small btn-delete"
                @click="deleteLegalDocument(doc.id)"
              >
                <i class="fas fa-trash"></i> {{ t("lps_btn_remove") }}
              </button>
            </div>
            <div class="legal-document-editor">
              <RichTextInput
                v-model="doc.content"
                :disabled="!hasEditPermission"
                :placeholder="t('lps_ph_legalContent')"
              />
            </div>
          </div>
        </div>

        <button
          v-if="hasEditPermission"
          type="button"
          class="btn-small btn-add"
          @click="addNewLegalDocument"
        >
          <i class="fas fa-plus"></i> {{ t("lps_btn_addLegalDoc") }}
        </button>

        <div class="info-box">
          <p>
            <strong
              ><i class="fas fa-exclamation-triangle"></i>
              {{ t("lps_word_important") }}:</strong
            >
            {{ t("lps_note_legalImportant") }}
          </p>
        </div>

        <div class="card-save-section">
          <button
            v-if="hasEditPermission"
            class="btn btn-primary"
            @click="saveLegalDocuments"
            :disabled="savingLegal"
          >
            <i class="fas fa-save"></i>
            {{ savingLegal ? t("lps_saving") : t("lps_btn_saveChanges") }}
          </button>
        </div>
      </div>
    </div>

    <!-- Language Settings -->
    <div class="settings-card" :class="{ collapsed: collapsedCards.language }">
      <div class="card-header" @click="toggleCard('language')">
        <div class="card-header-content">
          <h2>
            <i class="fas fa-globe"></i> {{ t("lps_card_language_title") }}
          </h2>
          <p>{{ t("lps_card_language_desc") }}</p>
        </div>
        <i class="fas fa-chevron-down card-collapse-icon"></i>
      </div>
      <div class="card-body">
        <!-- IP Detection -->
        <div class="toggle-option">
          <div class="toggle-option-info">
            <h3>{{ t("lps_ipDetection_title") }}</h3>
            <p>{{ t("lps_ipDetection_desc") }}</p>
          </div>
          <label
            class="toggle-switch"
            :class="{ disabled: !hasEditPermission }"
          >
            <input
              type="checkbox"
              v-model="ipLanguageDetection.isEnabled"
              :disabled="!hasEditPermission"
            />
            <span class="toggle-slider"></span>
          </label>
        </div>

        <!-- Default Language -->
        <div class="form-group">
          <label>
            {{ t("lps_label_defaultLang") }}
            <span class="label-description">{{
              t("lps_label_defaultLang_hint")
            }}</span>
          </label>
          <select
            v-model="ipLanguageDetection.defaultLanguageCode"
            :disabled="!hasEditPermission"
          >
            <option
              v-for="lang in languagePacks"
              :key="lang.languageCode"
              :value="lang.languageCode"
            >
              {{ lang.flagEmoji }} {{ lang.languageName }}
            </option>
          </select>
        </div>

        <!-- Language Packs -->
        <div class="form-group">
          <label>
            {{ t("lps_label_installedPacks") }}
            <span class="label-description">{{
              t("lps_label_installedPacks_hint")
            }}</span>
          </label>

          <div class="language-packs-list">
            <!-- Installed Language Packs -->
            <div
              v-for="lang in languagePacks"
              :key="lang.id"
              class="language-pack-item"
              :class="{ disabled: !lang.isEnabled }"
            >
              <div class="language-pack-info">
                <div class="language-pack-flag">{{ lang.flagEmoji }}</div>
                <div class="language-pack-details">
                  <div class="language-pack-name">{{ lang.languageName }}</div>
                  <div class="language-pack-meta">
                    {{
                      lang.isBuiltin
                        ? t("lps_langPack_builtin")
                        : t("lps_langPack_custom")
                    }}
                    ·
                    {{
                      tParams("lps_langPack_lastUpdated", "", {
                        date: formatDate(lang.lastUpdated),
                      })
                    }}
                  </div>
                </div>
              </div>
              <div class="language-pack-actions">
                <label
                  class="toggle-switch"
                  :class="{ disabled: !hasEditPermission }"
                >
                  <input
                    type="checkbox"
                    v-model="lang.isEnabled"
                    :disabled="!hasEditPermission"
                  />
                  <span class="toggle-slider"></span>
                </label>
              </div>
            </div>

            <!-- Available Language Packs (Not Installed) -->
            <div
              v-for="availableLang in availableLanguages"
              :key="availableLang.code"
              class="language-pack-item disabled"
            >
              <div class="language-pack-info">
                <div class="language-pack-flag">{{ availableLang.flag }}</div>
                <div class="language-pack-details">
                  <div class="language-pack-name">{{ availableLang.name }}</div>
                  <div class="language-pack-meta">
                    {{ t("lps_langPack_notInstalled") }}
                  </div>
                </div>
              </div>
              <div class="language-pack-actions">
                <button
                  type="button"
                  class="btn-small btn-upload"
                  @click="uploadLanguagePack(availableLang.code)"
                >
                  <i class="fas fa-upload"></i> {{ t("lps_btn_upload") }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <input
          ref="languagePackInput"
          type="file"
          accept=".json"
          @change="handleLanguagePackUpload"
          style="display: none"
        />

        <div class="info-box">
          <p>
            <strong
              ><i class="fas fa-info-circle"></i>
              {{ t("lps_word_howItWorks") }}:</strong
            >
            {{ t("lps_note_langHow") }}
          </p>
        </div>

        <div class="info-box info-box-warning">
          <p>
            <strong
              ><i class="fas fa-download"></i>
              {{ t("lps_word_format") }}:</strong
            >
            {{ t("lps_note_langFormat_part1") }}
            <a
              :href="languagePackTemplateUrl"
              download="language-pack-template.json"
              style="color: var(--color-brand); font-weight: 600"
              >{{ t("lps_note_langFormat_link") }}</a
            >
            {{ t("lps_note_langFormat_part2") }}
          </p>
        </div>

        <div class="card-save-section">
          <button
            v-if="hasEditPermission"
            class="btn btn-primary"
            @click="saveLanguageSettings"
            :disabled="savingLanguage"
          >
            <i class="fas fa-save"></i>
            {{ savingLanguage ? t("lps_saving") : t("lps_btn_saveChanges") }}
          </button>
        </div>
      </div>
    </div>

    <!-- Email Verification Settings -->
    <div class="settings-card" :class="{ collapsed: collapsedCards.email }">
      <div class="card-header" @click="toggleCard('email')">
        <div class="card-header-content">
          <h2>
            <i class="fas fa-envelope"></i> {{ t("lps_card_email_title") }}
          </h2>
          <p>{{ t("lps_card_email_desc") }}</p>
        </div>
        <i class="fas fa-chevron-down card-collapse-icon"></i>
      </div>
      <div class="card-body">
        <div class="toggle-option">
          <div class="toggle-option-info">
            <h3>{{ t("lps_emailRequire_title") }}</h3>
            <p>{{ t("lps_emailRequire_desc") }}</p>
          </div>
          <label
            class="toggle-switch"
            :class="{ disabled: !hasEditPermission }"
          >
            <input
              type="checkbox"
              v-model="emailVerification.isRequired"
              :disabled="!hasEditPermission"
            />
            <span class="toggle-slider"></span>
          </label>
        </div>

        <div class="form-group">
          <label>{{ t("lps_label_verificationTemplate") }}</label>
          <input
            type="text"
            v-model="emailVerification.emailSubject"
            :placeholder="t('lps_ph_emailSubject')"
            :disabled="!hasEditPermission"
          />
          <textarea
            v-model="emailVerification.emailTemplate"
            class="text-editor"
            rows="8"
            :placeholder="t('lps_ph_emailContent')"
            :disabled="!hasEditPermission"
          ></textarea>
        </div>

        <div class="form-group">
          <label>
            {{ t("lps_label_linkExpiry") }}
            <span class="label-description">{{
              t("lps_label_linkExpiry_hint")
            }}</span>
          </label>
          <input
            type="number"
            v-model.number="emailVerification.verificationLinkExpiryHours"
            min="1"
            max="168"
            :disabled="!hasEditPermission"
          />
        </div>

        <div class="toggle-option">
          <div class="toggle-option-info">
            <h3>{{ t("lps_allowResend_title") }}</h3>
            <p>{{ t("lps_allowResend_desc") }}</p>
          </div>
          <label
            class="toggle-switch"
            :class="{ disabled: !hasEditPermission }"
          >
            <input
              type="checkbox"
              v-model="emailVerification.allowResend"
              :disabled="!hasEditPermission"
            />
            <span class="toggle-slider"></span>
          </label>
        </div>

        <div class="info-box">
          <p>
            <strong
              ><i class="fas fa-shield-alt"></i>
              {{ t("lps_word_securityNote") }}:</strong
            >
            {{ t("lps_note_emailSecurity") }}
          </p>
        </div>

        <div class="card-save-section">
          <button
            v-if="hasEditPermission"
            class="btn btn-primary"
            @click="saveEmailVerification"
            :disabled="savingEmail"
          >
            <i class="fas fa-save"></i>
            {{ savingEmail ? t("lps_saving") : t("lps_btn_saveChanges") }}
          </button>
        </div>
      </div>
    </div>

    <!-- Success/Error Notifications -->
    <div
      v-if="notification.show"
      class="notification"
      :class="notification.type"
    >
      {{ notification.message }}
      <button @click="notification.show = false" class="close-btn">
        &times;
      </button>
    </div>
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { ref, onMounted, reactive, computed } from "vue";
import { useAuthStore } from "@/stores/auth";
import loginSettingsService from "@/services/loginSettingsService";
import RichTextInput from "@/components/common/RichTextInput.vue";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams } = useAdminI18n();
const languagePackTemplateUrl = `${import.meta.env.BASE_URL}language-pack-template.json`;

const authStore = useAuthStore();

// 权限检查
const hasReadonlyPermission = computed(() =>
  authStore.hasPermission("page_loginpagesettings_readonly"),
);
const hasEditPermission = computed(() =>
  authStore.hasPermission("page_loginpagesettings_edit"),
);

// State
const collapsedCards = reactive({
  branding: false,
  registration: false,
  countries: false,
  legal: false,
  language: false,
  email: false,
});

const branding = ref({
  logoType: "text",
  logoText: "BDX",
  logoImagePath: null,
  taglineEn: "",
  taglineZh: "",
  featuresContent: "",
});

const formFields = ref([]);
const passwordStrength = ref({
  strengthLevel: "medium",
  minLength: 8,
  requireLetters: true,
  requireNumbers: true,
});

const legalDocuments = ref([]);
const countries = ref([]);
const countrySearch = ref("");
const countryFilter = ref("all");
const originalCountryStatus = ref({});
const languagePacks = ref([]);

const filteredCountries = computed(() => {
  const keyword = countrySearch.value.trim().toLowerCase();
  return countries.value.filter((country) => {
    const matchesKeyword =
      !keyword ||
      country.name?.toLowerCase().includes(keyword) ||
      country.code?.toLowerCase().includes(keyword);
    const matchesStatus =
      countryFilter.value === "all" ||
      (countryFilter.value === "active" && country.isActive) ||
      (countryFilter.value === "inactive" && !country.isActive);
    return matchesKeyword && matchesStatus;
  });
});

const allFilteredSelected = computed(() => {
  if (filteredCountries.value.length === 0) return false;
  return filteredCountries.value.every((country) => country.isActive);
});
const ipLanguageDetection = ref({
  isEnabled: true,
  defaultLanguageCode: "en",
});

// Available languages that are not yet installed
const availableLanguages = ref([
  { code: "zh", name: "简体中文 (Simplified Chinese)", flag: "🇨🇳" },
  { code: "th", name: "ไทย (Thai)", flag: "🇹🇭" },
  { code: "vi", name: "Tiếng Việt (Vietnamese)", flag: "🇻🇳" },
  { code: "ja", name: "日本語 (Japanese)", flag: "🇯🇵" },
]);

const currentUploadingLanguage = ref(null);

const emailVerification = ref({
  isRequired: true,
  emailSubject: "Verify Your Account",
  emailTemplate: "",
  verificationLinkExpiryHours: 24,
  allowResend: true,
});

const passwordLevels = computed(() => [
  {
    value: "low",
    name: t("lps_pwd_low_name"),
    badge: t("lps_pwd_low_badge"),
    description: t("lps_pwd_low_desc"),
    requirements: t("lps_pwd_low_req"),
  },
  {
    value: "medium",
    name: t("lps_pwd_med_name"),
    badge: t("lps_pwd_med_badge"),
    description: t("lps_pwd_med_desc"),
    requirements: t("lps_pwd_med_req"),
  },
  {
    value: "high",
    name: t("lps_pwd_high_name"),
    badge: t("lps_pwd_high_badge"),
    description: t("lps_pwd_high_desc"),
    requirements: t("lps_pwd_high_req"),
  },
]);

const savingBranding = ref(false);
const savingRegistration = ref(false);
const savingCountries = ref(false);
const savingLegal = ref(false);
const savingLanguage = ref(false);
const savingEmail = ref(false);

// 拖拽相关状态
const draggedFieldIndex = ref(null);
const dragOverIndex = ref(null);

const notification = reactive({
  show: false,
  type: "success",
  message: "",
});

// Methods
const toggleCard = (cardName) => {
  collapsedCards[cardName] = !collapsedCards[cardName];
};

const showNotification = (type, message) => {
  notification.type = type;
  notification.message = message;
  notification.show = true;
  setTimeout(() => {
    notification.show = false;
  }, 3000);
};

// Branding
const handleLogoUpload = async (event) => {
  const file = event.target.files[0];
  if (!file) return;

  const formData = new FormData();
  formData.append("logo", file);

  try {
    const response = await loginSettingsService.uploadLogo(formData);
    branding.value.logoImagePath = response.data.logoPath;
    showNotification("success", t("lps_notify_logoOk"));
  } catch (error) {
    showNotification(
      "error",
      error.response?.data?.message || error.message || t("lps_err_uploadLogo"),
    );
  }
};

const removeLogo = () => {
  branding.value.logoImagePath = null;
  branding.value.logoType = "text";
};

const saveBranding = async () => {
  savingBranding.value = true;
  try {
    await loginSettingsService.updateBranding(branding.value);
    showNotification("success", t("lps_notify_brandingOk"));
  } catch (error) {
    showNotification(
      "error",
      error.response?.data?.message ||
        error.message ||
        t("lps_err_saveBranding"),
    );
  } finally {
    savingBranding.value = false;
  }
};

// Form Fields
const addNewField = () => {
  // 计算自定义字段数量（排除必填字段）
  const customFieldCount =
    formFields.value.filter((f) => !f.isMandatory).length + 1;
  formFields.value.push({
    id: null, // 新字段没有 ID，需要保存后由后端生成
    fieldId: `customField${Date.now()}`,
    fieldName: tParams("lps_newFieldName", "", { n: customFieldCount }),
    fieldDescription: "",
    fieldType: "text",
    isEnabled: true,
    isRequired: false,
    isMandatory: false,
    displayOrder: formFields.value.length + 1,
  });
};

const deleteField = async (fieldId) => {
  if (!confirm(t("lps_confirm_deleteField"))) return;

  try {
    if (fieldId !== null) {
      await loginSettingsService.deleteFormField(fieldId);
    }
    formFields.value = formFields.value.filter((f) => f.id !== fieldId);
    showNotification("success", t("lps_notify_fieldDeletedOk"));
  } catch (error) {
    showNotification(
      "error",
      error.response?.data?.message ||
        error.message ||
        t("lps_err_deleteField"),
    );
  }
};

const selectPasswordLevel = (level) => {
  passwordStrength.value.strengthLevel = level;
};

// 拖拽功能
const onFieldDragStart = (event, index) => {
  draggedFieldIndex.value = index;
  event.dataTransfer.effectAllowed = "move";
  event.dataTransfer.setData("text/html", event.target.innerHTML);
  // 添加拖拽样式
  event.target.style.opacity = "0.5";
};

const onFieldDragEnd = (event) => {
  event.target.style.opacity = "1";
  draggedFieldIndex.value = null;
  dragOverIndex.value = null;
};

const onFieldDragOver = (event, index) => {
  event.preventDefault();
  event.dataTransfer.dropEffect = "move";
  dragOverIndex.value = index;
};

const onFieldDragLeave = () => {
  dragOverIndex.value = null;
};

const onFieldDrop = (event, dropIndex) => {
  event.preventDefault();

  const dragIndex = draggedFieldIndex.value;

  if (dragIndex === null || dragIndex === dropIndex) {
    return;
  }

  // 重新排列数组
  const fields = [...formFields.value];
  const draggedField = fields[dragIndex];

  // 移除被拖拽的元素
  fields.splice(dragIndex, 1);

  // 在新位置插入
  fields.splice(dropIndex, 0, draggedField);

  // 更新 displayOrder
  fields.forEach((field, index) => {
    field.displayOrder = index + 1;
  });

  formFields.value = fields;

  // 清除拖拽状态
  draggedFieldIndex.value = null;
  dragOverIndex.value = null;

  // 提示用户保存
  showNotification("info", t("lps_notify_fieldOrderInfo"));
};

const saveRegistrationSettings = async () => {
  savingRegistration.value = true;
  try {
    // 1. 批量更新字段顺序（所有有 id 的字段）
    const orderUpdates = formFields.value
      .filter((field) => field.id)
      .map((field) => ({
        id: field.id,
        order: field.displayOrder,
      }));

    if (orderUpdates.length > 0) {
      await loginSettingsService.updateFieldsOrder(orderUpdates);
    }

    // 2. 保存字段内容变更和新字段
    for (const field of formFields.value) {
      if (field.id && !field.isMandatory) {
        // 更新非必填字段的其他属性（名称、描述、是否必填等）
        await loginSettingsService.updateFormField(field.id, {
          fieldName: field.fieldName,
          fieldDescription: field.fieldDescription,
          isEnabled: field.isEnabled,
          isRequired: field.isRequired,
          fieldType: field.fieldType,
        });
      } else if (!field.id && !field.isMandatory) {
        // 创建新字段
        const response = await loginSettingsService.createFormField(field);
        if (response.data && response.data.id) {
          field.id = response.data.id;
        }
      }
    }

    // 3. Save password strength
    await loginSettingsService.applyPasswordLevel(
      passwordStrength.value.strengthLevel,
    );

    showNotification("success", t("lps_notify_registrationOk"));
  } catch (error) {
    showNotification(
      "error",
      error.response?.data?.message ||
        error.message ||
        t("lps_err_saveRegistration"),
    );
  } finally {
    savingRegistration.value = false;
  }
};

// Country Settings
const loadCountries = async () => {
  const response = await loginSettingsService.getCountriesList();
  if (response.data && Array.isArray(response.data)) {
    countries.value = response.data.map((country) => ({
      ...country,
      isActive: !!country.isActive,
    }));
    originalCountryStatus.value = countries.value.reduce((acc, country) => {
      acc[country.id] = country.isActive;
      return acc;
    }, {});
  }
};

const saveCountries = async () => {
  savingCountries.value = true;
  try {
    const enableIds = [];
    const disableIds = [];

    countries.value.forEach((country) => {
      const original = originalCountryStatus.value[country.id];
      const current = !!country.isActive;
      if (original !== current) {
        if (current) {
          enableIds.push(country.id);
        } else {
          disableIds.push(country.id);
        }
      }
    });

    if (enableIds.length === 0 && disableIds.length === 0) {
      showNotification("info", t("lps_notify_noCountryChanges"));
      savingCountries.value = false;
      return;
    }

    await loginSettingsService.updateCountriesStatus({
      enableIds,
      disableIds,
    });

    enableIds.forEach((id) => {
      originalCountryStatus.value[id] = true;
    });
    disableIds.forEach((id) => {
      originalCountryStatus.value[id] = false;
    });

    showNotification("success", t("lps_notify_countriesOk"));
  } catch (error) {
    showNotification(
      "error",
      error.response?.data?.message ||
        error.message ||
        t("lps_err_updateCountries"),
    );
  } finally {
    savingCountries.value = false;
  }
};

const toggleSelectAllFiltered = () => {
  const nextState = !allFilteredSelected.value;
  filteredCountries.value.forEach((country) => {
    country.isActive = nextState;
  });
};

const getFlagCode = (code) => {
  if (!code) return "un";
  const upper = String(code).trim().toUpperCase();
  if (upper === "UK") return "gb";
  if (upper === "EL") return "gr";
  return upper.toLowerCase();
};

// Legal Documents
const addNewLegalDocument = () => {
  legalDocuments.value.push({
    id: null,
    documentType: `custom_${Date.now()}`,
    title: t("lps_legal_newDocTitle"),
    content: "",
    languageCode: "en",
    isActive: true,
    displayOrder: legalDocuments.value.length + 1,
  });
};

const deleteLegalDocument = async (docId) => {
  if (!confirm(t("lps_confirm_deleteDoc"))) return;

  if (docId) {
    try {
      await loginSettingsService.deleteLegalDocument(docId);
    } catch (error) {
      showNotification(
        "error",
        error.response?.data?.message ||
          error.message ||
          t("lps_err_deleteDoc"),
      );
      return;
    }
  }

  legalDocuments.value = legalDocuments.value.filter((d) => d.id !== docId);
  showNotification("success", t("lps_notify_docDeletedOk"));
};

const saveLegalDocuments = async () => {
  savingLegal.value = true;
  try {
    // 检查是否存在重复标题
    const seenTitles = new Map();
    for (let i = 0; i < legalDocuments.value.length; i++) {
      const rawTitle = legalDocuments.value[i].title || "";
      const title = rawTitle.trim();
      if (!title) continue;
      const key = title.toLowerCase();
      if (seenTitles.has(key)) {
        const firstIndex = seenTitles.get(key);
        showNotification(
          "error",
          tParams("lps_err_duplicateLegalTitle", "", {
            title,
            first: firstIndex + 1,
            second: i + 1,
          }),
        );
        return;
      }
      seenTitles.set(key, i);
    }

    for (const doc of legalDocuments.value) {
      if (doc.id) {
        await loginSettingsService.updateLegalDocument(doc.id, doc);
      } else {
        const response = await loginSettingsService.createLegalDocument(doc);
        doc.id = response.data.id;
      }
    }
    showNotification("success", t("lps_notify_legalOk"));
  } catch (error) {
    showNotification(
      "error",
      error.response?.data?.message || error.message || t("lps_err_saveLegal"),
    );
  } finally {
    savingLegal.value = false;
  }
};

// Language Settings
const uploadLanguagePack = (languageCode) => {
  currentUploadingLanguage.value = languageCode;
  const input = document.querySelector('input[type="file"][accept=".json"]');
  if (input) {
    input.click();
  }
};

const handleLanguagePackUpload = async (event) => {
  const file = event.target.files[0];
  if (!file) return;

  // Validate file type
  if (!file.name.endsWith(".json")) {
    showNotification("error", t("lps_err_jsonRequired"));
    event.target.value = "";
    return;
  }

  // Read and validate file content
  const reader = new FileReader();
  reader.onload = async (e) => {
    try {
      const languagePack = JSON.parse(e.target.result);

      // Validate language pack structure
      if (
        !languagePack.languageCode ||
        !languagePack.languageName ||
        !languagePack.translations
      ) {
        showNotification("error", t("lps_err_invalidLangPack"));
        return;
      }

      // Validate if it matches the selected language
      if (
        currentUploadingLanguage.value &&
        languagePack.languageCode !== currentUploadingLanguage.value
      ) {
        showNotification(
          "error",
          tParams("lps_err_langCodeMismatch", "", {
            expected: currentUploadingLanguage.value,
            got: languagePack.languageCode,
          }),
        );
        return;
      }

      // Upload to server
      const formData = new FormData();
      formData.append("languagePack", file);

      const response = await loginSettingsService.uploadLanguagePack(formData);

      // Reload language packs
      const langRes = await loginSettingsService.getLanguagePacks();
      if (langRes.data) {
        languagePacks.value = langRes.data;

        // Remove from available languages if successfully uploaded
        availableLanguages.value = availableLanguages.value.filter(
          (lang) => lang.code !== languagePack.languageCode,
        );
      }

      showNotification(
        "success",
        tParams("lps_notify_langPackOk", "", {
          name: languagePack.languageName,
        }),
      );
    } catch (error) {
      if (error instanceof SyntaxError) {
        showNotification("error", t("lps_err_jsonParse"));
      } else {
        showNotification(
          "error",
          error.response?.data?.message ||
            error.message ||
            t("lps_err_uploadLangPack"),
        );
      }
      console.error("Language pack upload error:", error);
    } finally {
      event.target.value = "";
      currentUploadingLanguage.value = null;
    }
  };

  reader.onerror = () => {
    showNotification("error", t("lps_err_readFile"));
    event.target.value = "";
  };

  reader.readAsText(file);
};

const saveLanguageSettings = async () => {
  savingLanguage.value = true;
  try {
    // Save IP detection settings and default language
    await loginSettingsService.updateIpLanguageDetection(
      ipLanguageDetection.value,
    );

    // Set default language
    if (ipLanguageDetection.value.defaultLanguageCode) {
      await loginSettingsService.setDefaultLanguage(
        ipLanguageDetection.value.defaultLanguageCode,
      );
    }

    // Update language packs enabled status
    for (const lang of languagePacks.value) {
      await loginSettingsService.updateLanguagePack(lang.languageCode, {
        isEnabled: lang.isEnabled,
      });
    }

    showNotification("success", t("lps_notify_languageOk"));
  } catch (error) {
    showNotification(
      "error",
      error.response?.data?.message ||
        error.message ||
        t("lps_err_saveLanguage"),
    );
  } finally {
    savingLanguage.value = false;
  }
};

// Email Verification
const saveEmailVerification = async () => {
  savingEmail.value = true;
  try {
    await loginSettingsService.updateEmailVerification(emailVerification.value);
    showNotification("success", t("lps_notify_emailOk"));
  } catch (error) {
    showNotification(
      "error",
      error.response?.data?.message || error.message || t("lps_err_saveEmail"),
    );
  } finally {
    savingEmail.value = false;
  }
};

// Utility
const formatDate = (dateString) => {
  if (!dateString) return t("lps_date_na");
  return new Date(dateString).toLocaleDateString();
};

// Load all settings
const loadAllSettings = async () => {
  try {
    // Load branding
    const brandingRes = await loginSettingsService.getBranding();
    if (brandingRes.data) {
      branding.value = brandingRes.data;
      branding.value.featuresContent = branding.value.featuresContent || "";
    }

    // Load form fields - 完全依赖 API 返回的数据
    const fieldsRes = await loginSettingsService.getFormFields();
    if (fieldsRes.data) {
      formFields.value = fieldsRes.data;
    }

    // Load password strength
    const passwordRes = await loginSettingsService.getPasswordStrength();
    if (passwordRes.data) {
      passwordStrength.value = passwordRes.data;
    }

    // Load legal documents
    const legalRes = await loginSettingsService.getLegalDocuments();
    if (legalRes.data) {
      legalDocuments.value = legalRes.data;
    }

    // Load language packs
    const langRes = await loginSettingsService.getLanguagePacks();
    if (langRes.data) {
      languagePacks.value = langRes.data;

      // Remove installed languages from available languages list
      const installedCodes = langRes.data.map((lang) => lang.languageCode);
      availableLanguages.value = availableLanguages.value.filter(
        (lang) => !installedCodes.includes(lang.code),
      );
    }

    // Load IP detection settings
    const ipRes = await loginSettingsService.getIpLanguageDetection();
    if (ipRes.data) {
      ipLanguageDetection.value = ipRes.data;
    }

    // Load email verification
    const emailRes = await loginSettingsService.getEmailVerification();
    if (emailRes.data) {
      emailVerification.value = emailRes.data;
    }

    // Load countries
    await loadCountries();
  } catch (error) {
    console.error("Failed to load settings:", error);
    showNotification(
      "error",
      error.response?.data?.message ||
        error.message ||
        t("lps_err_loadSettings"),
    );
  }
};

onMounted(() => {
  loadAllSettings();
});
</script>

<style scoped>
.container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 40px 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.page-title h1 {
  font-size: 28px;
  color: var(--color-ink);
  margin-bottom: 5px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.page-title h1 i {
  color: var(--color-brand);
}

.page-title p {
  font-size: 14px;
  color: var(--color-muted);
}

.page-actions {
  display: flex;
  align-items: center;
  gap: 20px;
}

.settings-card {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  margin-bottom: 30px;
  overflow: hidden;
  transition: all 0.3s ease;
}

.country-toolbar {
  display: flex;
  gap: 16px;
  align-items: center;
  justify-content: space-between;
  margin: 12px 0 16px;
  flex-wrap: wrap;
}

.country-search {
  position: relative;
  flex: 1 1 320px;
}

.country-search i {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-faint);
}

.country-search input {
  width: 100%;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
}

.country-filter {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.btn-country-toggle {
  white-space: nowrap;
}

.country-filter select {
  padding: 8px 10px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
  font-size: 14px;
}

.country-count {
  font-size: 14px;
  color: var(--color-muted);
}

.countries-table {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  height: 400px;
  overflow: auto;
}

.country-row {
  display: grid;
  grid-template-columns: 100px 1fr 140px 120px;
  gap: 12px;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid var(--color-surface-muted);
}

.country-row:last-child {
  border-bottom: none;
}

.country-header {
  background: var(--color-surface-soft);
  font-weight: 600;
  color: var(--color-text);
}

.country-col.code {
  font-weight: 600;
  color: var(--color-ink);
}

.country-col.name {
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 10px;
}

.country-col.phone {
  color: var(--color-muted);
  font-size: 14px;
}

.country-flag {
  width: 20px;
  height: 14px;
  border-radius: 2px;
  box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.06);
}

.country-col.status {
  display: flex;
  justify-content: flex-end;
}

@media (max-width: 900px) {
  .country-row {
    grid-template-columns: 80px 1fr;
    grid-template-areas:
      "code status"
      "name name"
      "phone phone";
  }

  .country-col.code {
    grid-area: code;
  }

  .country-col.status {
    grid-area: status;
  }

  .country-col.name {
    grid-area: name;
  }

  .country-col.phone {
    grid-area: phone;
  }
}

.card-header {
  background: var(--color-surface-soft);
  padding: 20px 30px;
  border-bottom: 2px solid var(--color-border);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: space-between;
  user-select: none;
  transition: background 0.2s ease;
}

.card-header:hover {
  background: var(--color-surface-muted);
}

.card-header-content h2 {
  font-size: 20px;
  color: var(--color-ink);
  margin-bottom: 5px;
}

.card-header-content h2 i {
  margin-right: 10px;
  color: var(--color-brand);
}

.card-header-content p {
  font-size: 14px;
  color: var(--color-muted);
}

.card-collapse-icon {
  font-size: 18px;
  color: var(--color-faint);
  transition: transform 0.3s ease;
}

.settings-card.collapsed .card-collapse-icon {
  transform: rotate(-90deg);
}

.card-body {
  padding: 30px;
  max-height: 5000px;
  overflow: hidden;
  transition:
    max-height 0.4s ease,
    opacity 0.3s ease,
    padding 0.3s ease;
  opacity: 1;
}

.settings-card.collapsed .card-body {
  max-height: 0;
  opacity: 0;
  padding-top: 0;
  padding-bottom: 0;
}

.settings-card.collapsed .card-header {
  border-bottom: none;
}

.form-group {
  margin-bottom: 25px;
}

.form-group label {
  display: block;
  margin-bottom: 10px;
  color: var(--color-ink);
  font-weight: 600;
  font-size: 14px;
}

.label-description {
  display: block;
  color: var(--color-muted);
  font-weight: 400;
  font-size: 14px;
  margin-top: 5px;
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group select {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition: all 0.3s ease;
  background: var(--color-surface);
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.text-editor {
  width: 100%;
  min-height: 200px;
  padding: 15px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
  resize: vertical;
  transition: all 0.3s ease;
}

.text-editor:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.logo-type-selector {
  display: flex;
  gap: 15px;
  margin-bottom: 15px;
}

.logo-type-selector label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.file-upload-area {
  border: 2px dashed var(--color-border-strong);
  border-radius: var(--radius-md);
  padding: 30px;
  text-align: center;
  transition: all 0.3s ease;
  cursor: pointer;
  background: var(--color-surface-soft);
}

.file-upload-area:hover {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
}

.upload-icon {
  font-size: 48px;
  margin-bottom: 15px;
  color: var(--color-faint);
}

.uploaded-file {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 15px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  margin-top: 15px;
}

.file-details {
  display: flex;
  align-items: center;
  gap: 12px;
}

.file-icon {
  font-size: 24px;
  color: var(--color-brand);
}

.remove-file {
  background: var(--color-danger-soft);
  color: var(--color-danger);
  border: none;
  padding: 8px 16px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  transition: all 0.2s ease;
}

.remove-file:hover {
  background: var(--color-danger-border);
  color: white;
}

/* Registration Fields */
.registration-fields-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 15px;
  margin-bottom: 15px;
}

.registration-field-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 20px;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  transition: all 0.3s ease;
  cursor: move;
  position: relative;
}

.registration-field-item:hover {
  border-color: var(--color-border-strong);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.registration-field-item.field-mandatory {
  opacity: 0.6;
}

.registration-field-item.field-disabled:not(.field-mandatory) {
  opacity: 0.5;
}

/* 拖拽样式 */
.registration-field-item.dragging {
  opacity: 0.5;
  border-color: var(--color-brand);
  background: var(--color-surface-soft);
}

.registration-field-item.drag-over {
  border-color: var(--color-brand);
  border-style: dashed;
  background: var(--color-brand-soft);
  transform: scale(1.02);
}

/* 拖拽手柄 */
.drag-handle {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  margin-right: 12px;
  color: var(--color-faint);
  cursor: grab;
  transition: all 0.2s ease;
  border-radius: var(--radius-sm);
}

.drag-handle:hover {
  color: var(--color-brand);
  background: rgba(var(--color-brand-rgb), 0.1);
}

.drag-handle:active {
  cursor: grabbing;
  color: var(--color-brand-strong);
  background: rgba(var(--color-brand-rgb), 0.2);
}

.drag-handle i {
  font-size: 16px;
}

.field-left {
  display: flex;
  align-items: center;
  gap: 15px;
  flex: 1;
}

.field-content {
  flex: 1;
}

.field-name-row {
  display: flex;
  align-items: center;
  margin-bottom: 4px;
}

.field-name-input {
  width: 100%;
  border: none;
  font-weight: 600;
  font-size: 15px;
  color: var(--color-ink);
  background: transparent;
  padding: 0;
}

.field-name-input:focus {
  outline: none;
}

.field-description-input {
  width: 100%;
  border: none;
  font-size: 14px;
  color: var(--color-muted);
  background: transparent;
  padding: 0;
}

.field-description-input:focus {
  outline: none;
}

.mandatory-badge {
  background: var(--color-brand-solid);
  color: white;
  padding: 2px 8px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
  margin-left: 8px;
  white-space: nowrap;
}

.field-right {
  display: flex;
  align-items: center;
  gap: 15px;
}

.required-checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.required-checkbox input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: var(--color-brand);
}

.required-checkbox input[type="checkbox"]:disabled {
  cursor: not-allowed;
}

.required-checkbox span {
  font-size: 14px;
  color: var(--color-text);
  font-weight: 500;
}

.field-mandatory .required-checkbox span {
  color: var(--color-faint);
}

.toggle-switch {
  position: relative;
  display: inline-block;
  width: 60px;
  height: 32px;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: var(--color-border-strong);
  transition: 0.4s;
  border-radius: 32px;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 24px;
  width: 24px;
  left: 4px;
  bottom: 4px;
  background-color: var(--color-surface);
  transition: 0.4s;
  border-radius: 50%;
}

input:checked + .toggle-slider {
  background: var(--color-brand-solid);
}

input:checked + .toggle-slider:before {
  transform: translateX(28px);
}

.toggle-switch.disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.toggle-switch.disabled .toggle-slider {
  cursor: not-allowed;
  background-color: var(--color-border) !important;
}

.toggle-switch.disabled input:checked + .toggle-slider {
  background: var(--color-success-soft) !important;
  opacity: 0.8;
}

.toggle-switch.disabled input:disabled + .toggle-slider {
  cursor: not-allowed;
}

.required-checkbox.disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.required-checkbox.disabled input[type="checkbox"]:disabled {
  cursor: not-allowed;
  opacity: 0.7;
}

.strength-option {
  cursor: pointer;
}

.strength-option:has(input:disabled) {
  cursor: not-allowed;
  opacity: 0.7;
}

/* Password Strength */
.password-strength-section {
  margin-top: 40px;
  padding-top: 30px;
  border-top: 2px solid var(--color-border);
}

.password-strength-options {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 15px;
  margin-top: 15px;
}

.strength-option {
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
}

.strength-option:hover {
  border-color: var(--color-border-strong);
  background: var(--color-surface-soft);
}

.strength-option.selected {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
}

.strength-option input {
  position: absolute;
  opacity: 0;
}

.strength-title {
  font-weight: 600;
  font-size: 16px;
  color: var(--color-ink);
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.strength-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
}

.strength-badge.low {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.strength-badge.medium {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.strength-badge.high {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.strength-description {
  font-size: 14px;
  color: var(--color-muted);
  line-height: 1.5;
}

.strength-requirements {
  margin-top: 10px;
  font-size: 14px;
  color: var(--color-text);
  line-height: 1.6;
}

.strength-requirements i {
  color: var(--color-success);
  margin-right: 6px;
}

/* Legal Documents */
.legal-documents-list {
  display: flex;
  flex-direction: column;
  gap: 20px;
  margin-bottom: 20px;
}

.legal-document-item {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 25px;
  transition: all 0.3s ease;
}

.legal-document-item:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.document-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  gap: 15px;
}

.document-title {
  flex: 1;
  padding: 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 15px;
  font-weight: 600;
  color: var(--color-ink);
}

.document-editor {
  width: 100%;
  min-height: 200px;
  padding: 15px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
  resize: vertical;
}

.legal-document-editor :deep(.document-editor) {
  min-height: 200px;
  max-height: 420px;
  overflow-y: auto;
  overflow-x: hidden;
}

/* Language Packs */
.language-packs-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 15px;
}

.language-pack-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 20px;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  transition: all 0.3s ease;
}

.language-pack-item:hover {
  border-color: var(--color-border-strong);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.language-pack-item.disabled {
  background: var(--color-surface-soft);
  opacity: 0.7;
}

.language-pack-info {
  display: flex;
  align-items: center;
  gap: 15px;
  flex: 1;
}

.language-pack-flag {
  font-size: 32px;
  line-height: 1;
}

.language-pack-details {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.language-pack-name {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-ink);
}

.language-pack-meta {
  font-size: 14px;
  color: var(--color-muted);
}

.language-pack-actions {
  display: flex;
  align-items: center;
  gap: 15px;
}

.language-pack-status {
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
}

.language-pack-status.default {
  background: var(--color-brand-solid);
  color: white;
}

/* Toggle Option */
.toggle-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  margin-bottom: 15px;
}

.toggle-option-info h3 {
  font-size: 16px;
  color: var(--color-ink);
  margin-bottom: 5px;
}

.toggle-option-info p {
  font-size: 14px;
  color: var(--color-muted);
  line-height: 1.5;
}

/* Buttons */
.btn {
  padding: 14px 32px;
  border-radius: var(--radius-md);
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
}

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 4px 15px rgba(var(--color-brand-rgb), 0.4);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(var(--color-brand-rgb), 0.5);
}

.btn-primary:disabled {
  background: var(--color-border-strong);
  color: var(--color-faint);
  box-shadow: none;
  cursor: not-allowed;
  opacity: 0.6;
}

.btn-small {
  padding: 8px 16px;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
}

.btn-add {
  background: var(--color-brand-solid);
  color: white;
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 14px 20px;
  font-size: 14px;
}

.btn-add:hover {
  background: var(--color-brand-strong);
  transform: translateY(-2px);
}

.btn-delete {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.btn-delete:hover {
  background: var(--color-danger-border);
  color: white;
}

.btn-upload {
  background: var(--color-brand-solid);
  color: white;
}

.btn-upload:hover {
  background: var(--color-brand-strong);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.3);
}

.card-save-section {
  display: flex;
  justify-content: flex-end;
  margin-top: 30px;
  padding-top: 20px;
  border-top: 2px solid var(--color-border);
}

/* Document Editor */
.document-editor {
  width: 100%;
  min-height: 150px;
  padding: 15px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  background: var(--color-surface);
  line-height: 1.6;
  transition: all 0.3s ease;
}

.document-editor:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.document-editor:empty:before {
  content: "Enter document content here...";
  color: var(--color-border-strong);
}

.document-editor ul,
.document-editor ol {
  padding-left: 20px;
  margin: 10px 0;
}

.document-editor li {
  margin: 6px 0;
}

.document-editor p {
  margin: 10px 0;
}

/* Info Box */
.info-box {
  background: var(--color-brand-soft);
  border-left: 4px solid var(--color-brand);
  padding: 15px 20px;
  border-radius: var(--radius-md);
  margin-top: 15px;
}

.info-box.info-box-warning {
  background: var(--color-danger-soft);
  border-left-color: var(--color-danger-border);
}

.info-box p {
  color: var(--color-text);
  font-size: 14px;
  line-height: 1.6;
  margin: 0;
}

.info-box i {
  margin-right: 6px;
}

/* Notification */
.notification {
  position: fixed;
  top: 20px;
  right: 20px;
  padding: 15px 40px 15px 20px;
  border-radius: var(--radius-md);
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
  z-index: 2000;
  animation: slideIn 0.3s ease;
}

.notification.success {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.notification.error {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.notification.info {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.notification .close-btn {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  font-size: 20px;
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

.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: var(--color-muted);
}

.empty-state i {
  font-size: 48px;
  margin-bottom: 16px;
  color: var(--color-border-strong);
}

.empty-state p {
  font-size: 14px;
  margin: 0;
}

@media (max-width: 768px) {
  .container {
    padding: 20px 15px;
  }

  .card-body {
    padding: 20px;
  }

  .password-strength-options {
    grid-template-columns: 1fr;
  }
}
</style>
