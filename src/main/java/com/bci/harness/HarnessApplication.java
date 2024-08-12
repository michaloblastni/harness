package com.bci.harness;

import com.bci.harness.dataimport.service.DataImportService;
import jakarta.annotation.PostConstruct;
import nz.net.ultraq.thymeleaf.layoutdialect.LayoutDialect;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.boot.builder.SpringApplicationBuilder;
import org.springframework.boot.web.servlet.support.SpringBootServletInitializer;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.security.authentication.AuthenticationManager;
import org.springframework.security.config.annotation.authentication.builders.AuthenticationManagerBuilder;
import org.springframework.security.config.annotation.web.builders.HttpSecurity;
import org.springframework.security.config.annotation.web.configuration.EnableWebSecurity;
import org.springframework.security.config.annotation.web.configurers.LogoutConfigurer;
import org.springframework.security.core.userdetails.UserDetailsService;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.security.web.SecurityFilterChain;
import org.springframework.security.web.csrf.CookieCsrfTokenRepository;
import org.springframework.web.servlet.LocaleResolver;
import org.springframework.web.servlet.config.annotation.InterceptorRegistry;
import org.springframework.web.servlet.config.annotation.WebMvcConfigurer;
import org.springframework.web.servlet.i18n.LocaleChangeInterceptor;
import org.springframework.web.servlet.i18n.SessionLocaleResolver;

import java.util.Locale;

@SpringBootApplication
public class HarnessApplication extends SpringBootServletInitializer {
	private DataImportService dataImportService;

	public HarnessApplication(DataImportService dataImportService) {
		this.dataImportService = dataImportService;
	}

	@Configuration
	public static class MvcConfig implements WebMvcConfigurer {
		@Bean
		public LayoutDialect thymeleafLayout() {
			return new LayoutDialect();
		}

		@Bean
		public LocaleResolver localeResolver() {
			SessionLocaleResolver slr = new SessionLocaleResolver();
			slr.setDefaultLocale(Locale.US);
			return slr;
		}
		@Bean
		public LocaleChangeInterceptor localeChangeInterceptor() {
			LocaleChangeInterceptor lci = new LocaleChangeInterceptor();
			lci.setParamName("lang");
			return lci;
		}

		@Override
		public void addInterceptors(InterceptorRegistry registry) {
			registry.addInterceptor(localeChangeInterceptor());
		}
	}

	@Configuration
	@EnableWebSecurity
	public static class SecurityConfig {
		@Autowired
		private UserDetailsService userDetailsService;

		@Bean
		public AuthenticationManager authenticationManager(HttpSecurity http) throws Exception {
			AuthenticationManagerBuilder auth = http.getSharedObject(AuthenticationManagerBuilder.class);
			auth.userDetailsService(userDetailsService).passwordEncoder(new BCryptPasswordEncoder());
			return auth.build();
		}

		@Bean
		public PasswordEncoder passwordEncoder() {
			return new BCryptPasswordEncoder();
		}

		@Bean
		public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
			http
					.csrf(csrf -> csrf
							.csrfTokenRepository(CookieCsrfTokenRepository.withHttpOnlyFalse())
					)
					.authorizeHttpRequests(requests ->
							requests.requestMatchers("/account/registration", "/account/login", "/account/logout").permitAll()
									.anyRequest().authenticated()
					)
					.formLogin((form) -> form
							.loginPage("/account/login")
							.permitAll()
					)
					.logout(LogoutConfigurer::permitAll);
			return http.build();
		}
	}

	@PostConstruct
	public void importData() {
		dataImportService.importData();
	}

	@Override
    protected SpringApplicationBuilder configure(SpringApplicationBuilder builder) {
        return builder.sources(HarnessApplication.class);
    }

	public static void main(String[] args) {
		SpringApplication.run(HarnessApplication.class, args);
	}
}
