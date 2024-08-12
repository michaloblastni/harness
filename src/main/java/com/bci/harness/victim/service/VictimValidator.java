package com.bci.harness.victim.service;

import com.bci.harness.victim.entity.Victim;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Component;
import org.springframework.validation.Errors;
import org.springframework.validation.ValidationUtils;
import org.springframework.validation.Validator;

import java.util.regex.Pattern;

@Component
public class VictimValidator implements Validator {
    private VictimService victimService;

    public VictimValidator(VictimService victimService) {
        this.victimService = victimService;
    }

    @Override
    public boolean supports(Class<?> aClass) {
        return Victim.class.equals(aClass);
    }

    @Override
    public void validate(Object o, Errors errors) {
        Victim user = (Victim) o;

        ValidationUtils.rejectIfEmptyOrWhitespace(errors, "username", "error.usernameCannotBeEmpty");
        if (user.getUsername().length() < 3 || user.getUsername().length() > 32) {
            errors.rejectValue("username", "error.invalidUsernameLength", "Username must have between 3 and 32 characters.");
        }

        if (victimService.findByUsername(user.getUsername()) != null) {
            errors.rejectValue("username", "error.duplicateUsername", "User with this username already exists.");
        }

        ValidationUtils.rejectIfEmptyOrWhitespace(errors, "password", "error.passwordCannotBeEmpty");
        if (user.getPassword().length() < 6 || user.getPassword().length() > 32) {
            errors.rejectValue("password", "error.invalidPasswordLength", "Password must have between 6 and 32 characters.");
        }

        if (!user.getPasswordConfirm().equals(user.getPassword())) {
            errors.rejectValue("passwordConfirm", "error.passwordMismatch", "Passwords do not match");
        }

        ValidationUtils.rejectIfEmptyOrWhitespace(errors, "email", "error.emailCannotBeEmpty");
    }
}
